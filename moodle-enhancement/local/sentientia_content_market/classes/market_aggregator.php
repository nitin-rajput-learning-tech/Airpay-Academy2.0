<?php
/**
 * Market aggregator — orchestrates all provider adapters.
 *
 * Responsibilities:
 *   1. Discover available provider adapters via the registry.
 *   2. For each configured provider: fetch → normalise → upsert to DB.
 *   3. Map imported items to the skills taxonomy (when local_sentientia_skillsai
 *      is present and the skills_mapping flag is ON).
 *   4. Write a sync log row for every provider run.
 *   5. Invalidate the catalog_listing MUC cache after a successful sync.
 *
 * Multi-tenant: every item row carries a costcenterid. The aggregator is
 * called per-tenant by the sync task. Site admins running a manual sync
 * may pass costcenterid=0 to sync into the "global" pool.
 *
 * @package    local_sentientia_content_market
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_content_market;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_content_market\adapter\provider_interface;

class market_aggregator {

    /** Maximum pages to fetch per provider per run (safety cap). */
    private const MAX_PAGES = 200;

    /**
     * Run a full sync for all configured providers for the given tenant.
     *
     * @param int $costcenterid Tenant root (0 = global / no tenant scope)
     * @return array Summary keyed by provider_key => {items_fetched, items_created, items_updated, items_retired, status, error}
     */
    public function sync_all(int $costcenterid = 0): array {
        $results = [];
        foreach ($this->get_active_providers() as $provider) {
            $results[$provider->get_provider_key()] = $this->sync_provider($provider, $costcenterid);
        }
        return $results;
    }

    /**
     * Run a sync for a single provider.
     *
     * @param provider_interface $provider
     * @param int                $costcenterid
     * @return array {items_fetched, items_created, items_updated, items_retired, status, error}
     */
    public function sync_provider(provider_interface $provider, int $costcenterid = 0): array {
        global $DB;

        $stats = [
            'provider'      => $provider->get_provider_key(),
            'items_fetched' => 0,
            'items_created' => 0,
            'items_updated' => 0,
            'items_retired' => 0,
            'status'        => 'ok',
            'error'         => null,
        ];

        if (!$provider->is_configured()) {
            $stats['status'] = 'disabled';
            $this->write_sync_log($stats, $costcenterid);
            return $stats;
        }

        try {
            $seen_external_ids = [];
            $page = 1;

            do {
                $items = $provider->fetch_courses($page, 100);
                if (empty($items)) {
                    break;
                }

                foreach ($items as $item) {
                    if (!($item instanceof catalog_item) || !$item->is_valid()) {
                        continue;
                    }
                    $stats['items_fetched']++;
                    $seen_external_ids[] = $item->external_id;

                    $upsert = $this->upsert_item($item, $costcenterid);
                    $stats['items_created'] += $upsert['created'];
                    $stats['items_updated'] += $upsert['updated'];

                    // Map skills after upsert when we have the DB id.
                    if ($upsert['id'] > 0 && !empty($item->skill_names)) {
                        $this->map_skills($upsert['id'], $item->skill_names);
                    }
                }

                $page++;
            } while ($provider->has_more_pages() && $page <= self::MAX_PAGES);

            // Retire items that were NOT seen in this sync (provider removed them).
            if (!empty($seen_external_ids)) {
                $stats['items_retired'] += $this->retire_missing(
                    $provider->get_provider_key(),
                    $seen_external_ids,
                    $costcenterid
                );
            }

        } catch (\Throwable $e) {
            $stats['status'] = 'failed';
            $stats['error']  = $e->getMessage();
            debugging("market_aggregator: sync failed for {$provider->get_provider_key()}: {$e->getMessage()}", DEBUG_DEVELOPER);
        }

        // Invalidate catalog listing cache after sync.
        $this->invalidate_cache($costcenterid);

        $this->write_sync_log($stats, $costcenterid);
        return $stats;
    }

    /**
     * Upsert a single catalog item into {local_sentientia_cm_item}.
     *
     * @return array{id: int, created: int, updated: int}
     */
    private function upsert_item(catalog_item $item, int $costcenterid): array {
        global $DB;

        // Match within the tenant: the same external course may legitimately exist in more
        // than one tenant, so costcenterid is part of the uniqueness key (see idx_provider_ext).
        $existing = $DB->get_record('local_sentientia_cm_item', [
            'provider'     => $item->provider,
            'external_id'  => $item->external_id,
            'costcenterid' => $costcenterid,
        ]);

        $now = time();

        if ($existing) {
            // Update fields that may have changed since last sync.
            $existing->title         = $item->title;
            $existing->description   = $item->description;
            $existing->thumbnail_url = $item->thumbnail_url;
            $existing->provider_url  = $item->provider_url;
            $existing->duration_mins = $item->duration_mins;
            $existing->language      = $item->language;
            $existing->level         = $item->level;
            $existing->content_type  = $item->content_type;
            $existing->price_usd     = $item->price_usd;
            $existing->raw_payload   = json_encode($item->raw_payload);
            $existing->status        = 'active';
            $existing->last_synced   = $now;
            $existing->timemodified  = $now;
            $DB->update_record('local_sentientia_cm_item', $existing);
            return ['id' => (int)$existing->id, 'created' => 0, 'updated' => 1];
        }

        // Insert new item.
        $record = new \stdClass();
        $record->provider      = $item->provider;
        $record->external_id   = $item->external_id;
        $record->costcenterid  = $costcenterid;
        $record->title         = $item->title;
        $record->description   = $item->description;
        $record->thumbnail_url = $item->thumbnail_url;
        $record->provider_url  = $item->provider_url;
        $record->duration_mins = $item->duration_mins;
        $record->language      = $item->language;
        $record->level         = $item->level;
        $record->content_type  = $item->content_type;
        $record->price_usd     = $item->price_usd;
        $record->raw_payload   = json_encode($item->raw_payload);
        $record->status        = 'active';
        $record->last_synced   = $now;
        $record->timecreated   = $now;
        $record->timemodified  = $now;

        $id = $DB->insert_record('local_sentientia_cm_item', $record);
        return ['id' => (int)$id, 'created' => 1, 'updated' => 0];
    }

    /**
     * Mark items as 'retired' when they are no longer present in the provider's
     * catalog. Retired items remain in the DB but are hidden from the browse UI.
     *
     * @param string   $provider_key
     * @param string[] $seen_ids      external_id values seen in this sync run
     * @param int      $costcenterid
     * @return int Number of items retired
     */
    private function retire_missing(string $provider_key, array $seen_ids, int $costcenterid): int {
        global $DB;

        if (empty($seen_ids)) {
            return 0;
        }

        [$insql, $params] = $DB->get_in_or_equal($seen_ids, SQL_PARAMS_NAMED, 'eid');
        $params['prov']  = $provider_key;
        $params['cid']   = $costcenterid;
        $params['ts']    = time();

        // Update active items whose external_id was NOT in the seen set.
        $DB->execute(
            "UPDATE {local_sentientia_cm_item}
                SET status = 'retired', timemodified = :ts
              WHERE provider = :prov
                AND costcenterid = :cid
                AND status = 'active'
                AND external_id NOT $insql",
            $params
        );

        return $DB->count_records('local_sentientia_cm_item', [
            'provider'    => $provider_key,
            'costcenterid'=> $costcenterid,
            'status'      => 'retired',
        ]);
    }

    /**
     * Map provider-supplied skill name strings to the skills taxonomy.
     *
     * When local_sentientia_skillsai is installed and the skills_mapping flag
     * is ON, attempts a term lookup and writes to {local_sentientia_cm_skill_map}.
     * Degrades gracefully when the plugin is absent — writes provider-supplied
     * names directly with skill_id=0.
     *
     * @param int      $item_id
     * @param string[] $skill_names Provider-supplied skill name strings
     */
    private function map_skills(int $item_id, array $skill_names): void {
        global $DB;

        // Check if skills mapping feature is enabled.
        if (!class_exists('\local_sentientia_platform\feature_flags')) {
            return;
        }
        if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.content_market.skills_mapping.enabled')) {
            return;
        }

        $now = time();

        foreach ($skill_names as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $skill_id = 0;

            // Attempt taxonomy resolution via local_sentientia_skillsai.
            if (class_exists('\local_sentientia_skillsai\taxonomy')) {
                try {
                    $term = \local_sentientia_skillsai\taxonomy::find_term($name);
                    if ($term) {
                        $skill_id = (int)$term->id;
                        $name     = $term->name;  // Use canonical taxonomy name.
                    }
                } catch (\Throwable $e) {
                    debugging("market_aggregator: skill taxonomy lookup failed for '$name': " . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }

            // Insert only if this (item, skill_id) mapping doesn't already exist.
            $exists = $DB->record_exists('local_sentientia_cm_skill_map', [
                'item_id'  => $item_id,
                'skill_id' => $skill_id,
            ]);

            if (!$exists) {
                $DB->insert_record('local_sentientia_cm_skill_map', (object)[
                    'item_id'    => $item_id,
                    'skill_id'   => $skill_id,
                    'skill_name' => $name,
                    'source'     => 'provider',
                    'confidence' => null,
                    'timecreated'=> $now,
                ]);
            }
        }
    }

    /**
     * Write a row to the sync log table.
     */
    private function write_sync_log(array $stats, int $costcenterid): void {
        global $DB;
        $DB->insert_record('local_sentientia_cm_sync_log', (object)[
            'provider'      => $stats['provider'],
            'costcenterid'  => $costcenterid,
            'items_fetched' => $stats['items_fetched'],
            'items_created' => $stats['items_created'],
            'items_updated' => $stats['items_updated'],
            'items_retired' => $stats['items_retired'],
            'status'        => $stats['status'],
            'errormsg'      => $stats['error'],
            'timecreated'   => time(),
        ]);
    }

    /**
     * Invalidate the catalog_listing MUC cache for the given tenant.
     *
     * @param int $costcenterid
     */
    private function invalidate_cache(int $costcenterid): void {
        $cache = \cache::make('local_sentientia_content_market', 'catalog_listing');
        $cache->delete("listing_{$costcenterid}");
    }

    /**
     * Get all registered provider instances that are configured and enabled.
     *
     * @return provider_interface[]
     */
    public function get_active_providers(): array {
        $all = $this->get_all_providers();
        return array_filter($all, fn($p) => $p->is_configured());
    }

    /**
     * Get all registered provider instances (regardless of configuration state).
     * Extend this list when adding new provider adapters.
     *
     * @return provider_interface[]
     */
    public function get_all_providers(): array {
        return [
            new \local_sentientia_content_market\adapter\go1_provider(),
            new \local_sentientia_content_market\adapter\udemy_business_provider(),
            new \local_sentientia_content_market\adapter\coursera_provider(),
            new \local_sentientia_content_market\adapter\skillsoft_provider(),
            new \local_sentientia_content_market\adapter\mock_provider(),
        ];
    }

    /**
     * Search the local catalog with optional filters.
     *
     * All results are scoped to $costcenterid (or global items with costcenterid=0
     * are included — admins may seed a global pool shared across tenants).
     *
     * @param int    $costcenterid   Tenant root
     * @param string $query          Full-text search string (empty = all)
     * @param string $provider       Filter to one provider key (empty = all)
     * @param string $content_type   Filter to one content type (empty = all)
     * @param string $level          Filter to one level (empty = all)
     * @param int    $skill_id       Filter items mapped to a skill (0 = no filter)
     * @param int    $page           1-based page number
     * @param int    $page_size      Items per page
     * @return array{items: array, total: int}
     */
    public function search(int $costcenterid, string $query = '', string $provider = '',
                           string $content_type = '', string $level = '',
                           int $skill_id = 0, int $page = 1, int $page_size = 20): array {
        global $DB;

        $where  = ["i.status = 'active'"];
        $params = [];

        // Tenant scope: show items for this tenant OR global items (cid=0).
        $where[]            = '(i.costcenterid = :cid OR i.costcenterid = 0)';
        $params['cid']      = $costcenterid;

        if ($query !== '') {
            $query_escaped  = $DB->sql_like_escape($query);
            $where[]        = '(' . $DB->sql_like('i.title', ':qtitle', false)
                            . ' OR ' . $DB->sql_like('i.description', ':qdesc', false) . ')';
            $params['qtitle']= '%' . $query_escaped . '%';
            $params['qdesc'] = '%' . $query_escaped . '%';
        }

        if ($provider !== '') {
            $where[]          = 'i.provider = :prov';
            $params['prov']   = $provider;
        }

        if ($content_type !== '') {
            $where[]               = 'i.content_type = :ctype';
            $params['ctype']       = $content_type;
        }

        if ($level !== '') {
            $where[]         = 'i.level = :lvl';
            $params['lvl']   = $level;
        }

        $join = '';
        if ($skill_id > 0) {
            $join              = "JOIN {local_sentientia_cm_skill_map} sm ON sm.item_id = i.id AND sm.skill_id = :skid";
            $params['skid']    = $skill_id;
        }

        $where_sql = implode(' AND ', $where);

        $total = (int) $DB->get_field_sql(
            "SELECT COUNT(i.id) FROM {local_sentientia_cm_item} i $join WHERE $where_sql",
            $params
        );

        $offset = ($page - 1) * $page_size;
        $items  = $DB->get_records_sql(
            "SELECT i.* FROM {local_sentientia_cm_item} i $join
              WHERE $where_sql
           ORDER BY i.title ASC",
            $params, $offset, $page_size
        );

        return ['items' => array_values($items), 'total' => $total];
    }
}
