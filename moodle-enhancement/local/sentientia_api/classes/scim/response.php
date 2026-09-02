<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\scim;

defined('MOODLE_INTERNAL') || die();

/**
 * SCIM 2.0 response envelopes (RFC 7644). A response is a plain array
 * {status, body, headers} so the handler stays transport-neutral and unit-testable.
 *
 * @package local_sentientia_api
 */
class response {

    public const SCHEMA_USER  = 'urn:ietf:params:scim:schemas:core:2.0:User';
    public const SCHEMA_GROUP = 'urn:ietf:params:scim:schemas:core:2.0:Group';
    public const SCHEMA_LIST  = 'urn:ietf:params:scim:api:messages:2.0:ListResponse';
    public const SCHEMA_ERROR = 'urn:ietf:params:scim:api:messages:2.0:Error';
    public const SCHEMA_PATCH = 'urn:ietf:params:scim:api:messages:2.0:PatchOp';
    public const SCHEMA_SPC   = 'urn:ietf:params:scim:schemas:core:2.0:ServiceProviderConfig';
    public const SCHEMA_RT    = 'urn:ietf:params:scim:schemas:core:2.0:ResourceType';
    public const SCHEMA_SCHEMA = 'urn:ietf:params:scim:schemas:core:2.0:Schema';

    /** @var string */
    public const CONTENT_TYPE = 'application/scim+json; charset=utf-8';

    /**
     * @param int   $status
     * @param array|null $body null => empty body (204)
     * @param array $headers
     * @return array{status:int,body:?array,headers:array}
     */
    public static function ok(int $status, ?array $body, array $headers = []): array {
        return ['status' => $status, 'body' => $body, 'headers' => $headers];
    }

    /**
     * @param int         $status
     * @param string      $detail
     * @param string|null $scimtype
     * @return array{status:int,body:array,headers:array}
     */
    public static function error(int $status, string $detail, ?string $scimtype = null): array {
        $body = [
            'schemas' => [self::SCHEMA_ERROR],
            'status'  => (string) $status,
            'detail'  => $detail,
        ];
        if ($scimtype !== null) {
            $body['scimType'] = $scimtype;
        }
        return ['status' => $status, 'body' => $body, 'headers' => []];
    }

    /**
     * @param scim_exception $e
     * @return array
     */
    public static function from_exception(scim_exception $e): array {
        return self::error($e->status, $e->getMessage(), $e->scimtype);
    }

    /**
     * ListResponse envelope. startIndex is 1-based per RFC 7644 §3.4.2.4.
     *
     * @param array $resources
     * @param int   $total
     * @param int   $startindex
     * @param int   $count
     * @return array
     */
    public static function list(array $resources, int $total, int $startindex, int $count): array {
        return self::ok(200, [
            'schemas'      => [self::SCHEMA_LIST],
            'totalResults' => $total,
            'startIndex'   => $startindex,
            'itemsPerPage' => $count,
            'Resources'    => array_values($resources),
        ]);
    }

    /**
     * Static discovery documents.
     *
     * @param string $baseurl
     * @return array
     */
    public static function service_provider_config(string $baseurl): array {
        return self::ok(200, [
            'schemas' => [self::SCHEMA_SPC],
            'documentationUri' => $baseurl . '/../../index.php',
            'patch'  => ['supported' => true],
            'bulk'   => ['supported' => false, 'maxOperations' => 0, 'maxPayloadSize' => 0],
            'filter' => ['supported' => true, 'maxResults' => 200],
            'changePassword' => ['supported' => false],
            'sort'   => ['supported' => false],
            'etag'   => ['supported' => true],
            'authenticationSchemes' => [[
                'type' => 'oauthbearertoken',
                'name' => 'OAuth Bearer Token',
                'description' => 'Per-client bearer token issued by the Sentientia administrator.',
                'primary' => true,
            ]],
            'meta' => ['resourceType' => 'ServiceProviderConfig', 'location' => $baseurl . '/ServiceProviderConfig'],
        ]);
    }

    /**
     * @param string $baseurl
     * @return array
     */
    public static function resource_types(string $baseurl): array {
        return self::list([[
            'schemas'  => [self::SCHEMA_RT],
            'id'       => 'User',
            'name'     => 'User',
            'endpoint' => '/Users',
            'schema'   => self::SCHEMA_USER,
            'meta'     => ['resourceType' => 'ResourceType', 'location' => $baseurl . '/ResourceTypes/User'],
        ], [
            'schemas'  => [self::SCHEMA_RT],
            'id'       => 'Group',
            'name'     => 'Group',
            'endpoint' => '/Groups',
            'schema'   => self::SCHEMA_GROUP,
            'meta'     => ['resourceType' => 'ResourceType', 'location' => $baseurl . '/ResourceTypes/Group'],
        ]], 2, 1, 2);
    }

    /**
     * @param string $baseurl
     * @return array
     */
    public static function schemas(string $baseurl): array {
        $attr = function (string $name, string $type, bool $required = false, bool $multi = false, string $mutability = 'readWrite') {
            return ['name' => $name, 'type' => $type, 'multiValued' => $multi, 'required' => $required,
                    'caseExact' => false, 'mutability' => $mutability, 'returned' => 'default', 'uniqueness' => 'none'];
        };
        return self::list([[
            'schemas'     => [self::SCHEMA_SCHEMA],
            'id'          => self::SCHEMA_USER,
            'name'        => 'User',
            'description' => 'Sentientia LMS user account',
            'attributes'  => [
                $attr('userName', 'string', true) + ['uniqueness' => 'server'],
                $attr('externalId', 'string'),
                $attr('displayName', 'string'),
                $attr('active', 'boolean'),
                ['name' => 'name', 'type' => 'complex', 'multiValued' => false, 'required' => false,
                 'mutability' => 'readWrite', 'returned' => 'default',
                 'subAttributes' => [$attr('givenName', 'string'), $attr('familyName', 'string'), $attr('formatted', 'string', false, false, 'readOnly')]],
                ['name' => 'emails', 'type' => 'complex', 'multiValued' => true, 'required' => true,
                 'mutability' => 'readWrite', 'returned' => 'default',
                 'subAttributes' => [$attr('value', 'string'), $attr('type', 'string'), $attr('primary', 'boolean')]],
            ],
            'meta' => ['resourceType' => 'Schema', 'location' => $baseurl . '/Schemas/' . self::SCHEMA_USER],
        ], [
            'schemas'     => [self::SCHEMA_SCHEMA],
            'id'          => self::SCHEMA_GROUP,
            'name'        => 'Group',
            'description' => 'Organisation node (read-only structure; membership writable)',
            'attributes'  => [
                $attr('displayName', 'string', true, false, 'readOnly'),
                $attr('externalId', 'string', false, false, 'readOnly'),
                ['name' => 'members', 'type' => 'complex', 'multiValued' => true, 'required' => false,
                 'mutability' => 'readWrite', 'returned' => 'default',
                 'subAttributes' => [$attr('value', 'string'), $attr('display', 'string', false, false, 'readOnly'),
                                     $attr('$ref', 'reference', false, false, 'readOnly')]],
            ],
            'meta' => ['resourceType' => 'Schema', 'location' => $baseurl . '/Schemas/' . self::SCHEMA_GROUP],
        ]], 2, 1, 2);
    }
}
