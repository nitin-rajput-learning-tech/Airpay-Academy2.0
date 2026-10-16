"""Pilot harness tests (standard library unittest). Run with:  python -I run_pilot.py --selftest

No test makes a network call, reads a credential or touches anything outside a temporary directory.
Node.js-dependent tests are skipped when Node is not installed.
"""
from __future__ import annotations

import copy
import json
import shutil
import subprocess
import sys
import tempfile
import unittest
import zipfile
from pathlib import Path

HERE = Path(__file__).resolve().parent.parent
if str(HERE) not in sys.path:
    sys.path.insert(0, str(HERE))

from lib import audit, pii, pipeline, statetoken, translate, tts  # noqa: E402
from lib.common import HarnessError, read_json, sha256_file  # noqa: E402
from lib.packager import STANDARDS, carried_languages, compile_package, restrict_document, write_zip  # noqa: E402
from lib.schema_lite import validate_document  # noqa: E402
from lib.validate_package import validate_package  # noqa: E402

COURSE = HERE / "fixtures" / "course-upi-dispute.json"
SCHEMA = HERE / "schema" / "sentientia-course.schema.json"
SOURCE = HERE / "fixtures" / "sample-sop-upi-dispute.md"
NODE = shutil.which("node")


def fixture() -> dict:
    return copy.deepcopy(read_json(COURSE))


def validate(doc: dict, **kwargs):
    return validate_document(doc, read_json(SCHEMA), **kwargs)


def verhoeff_check_digit(digits: str) -> str:
    """Append the Verhoeff check digit so tests can build a valid Aadhaar-shaped number."""
    for candidate in "0123456789":
        if pii.verhoeff_ok(digits + candidate):
            return digits + candidate
    raise AssertionError("no check digit")


def luhn_complete(prefix: str) -> str:
    for candidate in "0123456789":
        if pii.luhn_ok(prefix + candidate):
            return prefix + candidate
    raise AssertionError("no check digit")


class FixtureValidation(unittest.TestCase):
    def test_fixture_passes_all_rules(self) -> None:
        res = validate(fixture(), preview=True)
        self.assertTrue(res.ok, [str(i) for i in res.errors])
        for rule, status in res.rule_status.items():
            self.assertNotEqual(status, "fail", rule)

    def test_dangling_edge_is_r04(self) -> None:
        doc = fixture()
        scenario = next(b for b in doc["blocks"] if b["type"] == "scenario")
        scenario["nodes"][0]["edges"][0]["to"] = "does_not_exist"
        res = validate(doc, preview=True)
        self.assertEqual(res.rule_status["R04"], "fail")

    def test_unreachable_node_is_r04(self) -> None:
        doc = fixture()
        scenario = next(b for b in doc["blocks"] if b["type"] == "scenario")
        scenario["nodes"].append({"id": "orphan", "kind": "terminal", "terminal_outcome": "failure", "prompt": {"en": "Nobody reaches this."}})
        self.assertEqual(validate(doc, preview=True).rule_status["R04"], "fail")

    def test_cycle_is_rejected_unless_allowed(self) -> None:
        doc = fixture()
        scenario = next(b for b in doc["blocks"] if b["type"] == "scenario")
        n3 = next(n for n in scenario["nodes"] if n["id"] == "n3")
        n3["edges"].append({"id": "e3c", "text": {"en": "Start over."}, "to": "n1", "score_delta": 0, "outcome": "recoverable", "correct": False})
        self.assertEqual(validate(doc, preview=True).rule_status["R04"], "fail")
        scenario["allow_cycles"] = True
        self.assertNotEqual(validate(doc, preview=True).rule_status["R04"], "fail")

    def test_missing_base_language_is_r01(self) -> None:
        doc = fixture()
        del doc["blocks"][0]["title"]["en"]
        self.assertEqual(validate(doc, preview=True).rule_status["R01"], "fail")

    def test_duplicate_ids_are_r10(self) -> None:
        doc = fixture()
        doc["blocks"][1]["id"] = "b01"
        self.assertEqual(validate(doc, preview=True).rule_status["R10"], "fail")

    def test_multichoice_needs_exactly_one_correct_option_r06(self) -> None:
        doc = fixture()
        quiz = next(b for b in doc["blocks"] if b["type"] == "quiz")
        for option in quiz["questions"][0]["options"]:
            option["correct"] = True
        self.assertEqual(validate(doc, preview=True).rule_status["R06"], "fail")

    def test_missing_protected_term_is_r07(self) -> None:
        doc = fixture()
        doc["blocks"][1]["title"]["hi"] = "बिना गोपनीय जानकारी माँगे सत्यापन"  # drops nothing required here ...
        doc["blocks"][1]["points"][1]["hi"] = "PIN, कार्ड नंबर या CVV कभी न माँगें"  # ... but this drops UPI and OTP
        self.assertEqual(validate(doc, preview=True).rule_status["R07"], "fail")

    def test_unapproved_document_needs_preview_r09(self) -> None:
        doc = fixture()
        doc["provenance"]["review"]["state"] = "in_review"
        self.assertEqual(validate(doc, preview=False).rule_status["R09"], "fail")
        self.assertNotEqual(validate(doc, preview=True).rule_status["R09"], "fail")

    def test_state_budget_blows_up_for_a_huge_scenario_r05(self) -> None:
        doc = fixture()
        scenario = next(b for b in doc["blocks"] if b["type"] == "scenario")
        previous = "n3"
        # chain of 120 decision nodes with long edge ids pushes the worst-case token past 3,500 characters
        extra = []
        for i in range(120):
            nid = f"chain_{i:03d}"
            nxt = f"chain_{i + 1:03d}" if i < 119 else "t_success"
            extra.append({"id": nid, "kind": "decision", "prompt": {"en": "Next?"}, "edges": [
                {"id": f"edge_with_a_long_identifier_{i:03d}_a", "text": {"en": "A"}, "to": nxt, "score_delta": 1, "outcome": "acceptable", "correct": True},
                {"id": f"edge_with_a_long_identifier_{i:03d}_b", "text": {"en": "B"}, "to": nxt, "score_delta": 0, "outcome": "recoverable", "correct": False},
            ]})
        n3 = next(n for n in scenario["nodes"] if n["id"] == previous)
        n3["edges"][0]["to"] = "chain_000"
        scenario["nodes"].extend(extra)
        res = validate(doc, preview=True)
        self.assertEqual(res.rule_status["R05"], "fail")

    def test_media_path_traversal_is_r08(self) -> None:
        doc = fixture()
        doc["blocks"][0]["audio"] = {"en": {"path": "../../etc/passwd"}}
        self.assertEqual(validate(doc, preview=True).rule_status["R08"], "fail")


class PiiScanner(unittest.TestCase):
    def test_valid_aadhaar_is_high_confidence(self) -> None:
        number = verhoeff_check_digit("23456789012")
        found = pii.scan_text(f"Customer UID {number[:4]} {number[4:8]} {number[8:]} on file", "t")
        self.assertEqual([f.kind for f in found], ["aadhaar"])
        self.assertEqual(found[0].confidence, "high")
        self.assertNotIn(number, found[0].masked)

    def test_devanagari_digits_are_normalised(self) -> None:
        number = verhoeff_check_digit("23456789012")
        deva = number.translate(str.maketrans("0123456789", "०१२३४५६७८९"))
        self.assertEqual([f.kind for f in pii.scan_text(deva, "t")], ["aadhaar"])

    def test_pan_mobile_email_ifsc_card(self) -> None:
        card = luhn_complete("411111111111111")
        text = f"PAN ABCDE1234F, call +91 98765 43210, mail rohan.k@example.com, IFSC HDFC0001234, card {card}"
        kinds = sorted(f.kind for f in pii.scan_text(text, "t"))
        self.assertEqual(kinds, ["card", "email", "ifsc", "mobile", "pan"])

    def test_plain_course_text_is_clean(self) -> None:
        self.assertEqual(pii.scan_text("The amount is 12,400 rupees; escalate over 50,000; UTR is collected; reference 2026101500123", "t"), [])

    def test_fixture_and_sop_are_clean(self) -> None:
        self.assertEqual(pii.scan_document(fixture()), [])
        self.assertEqual(pii.scan_file(SOURCE), [])

    def test_values_are_never_stored_in_full(self) -> None:
        card = luhn_complete("411111111111111")
        finding = pii.scan_text(card, "t")[0]
        self.assertNotIn(card, json.dumps(finding.as_dict()))


class StateToken(unittest.TestCase):
    STATE = {
        "lang": "hi", "block_index": 4, "visited": [True, True, False, True, False, True],
        "scenarios": {"s01": ["e1a", "e2b"]},
        "quizzes": {"q01": {"submitted": True, "answers": {
            "q01_1": {"type": "c", "value": "b"}, "q01_3": {"type": "m", "value": ["a", "c"]},
            "q01_4": {"type": "t", "value": "f"}, "q01_5": {"type": "x", "value": [["p1", "p2"], ["p2", "p1"]]}}}},
    }

    def test_roundtrip(self) -> None:
        token = statetoken.encode_state(self.STATE)
        back = statetoken.decode_state(token, 6)
        self.assertEqual(back["lang"], "hi")
        self.assertEqual(back["visited"], self.STATE["visited"])
        self.assertEqual(back["scenarios"], self.STATE["scenarios"])
        self.assertEqual(back["quizzes"]["q01"]["answers"]["q01_5"]["value"], [["p1", "p2"], ["p2", "p1"]])
        self.assertTrue(back["quizzes"]["q01"]["submitted"])

    def test_compression_roundtrip_and_ratio(self) -> None:
        token = statetoken.encode_state(self.STATE)
        big = token + ";" + ";".join(f"x{i}:{'e' * 20}" for i in range(40))
        self.assertEqual(statetoken.decompress_token(statetoken.compress_token(big)), big)
        self.assertLess(len(statetoken.compress_token(big)), len(big))

    @unittest.skipUnless(NODE, "Node.js not installed")
    def test_python_and_javascript_encoders_agree(self) -> None:
        script = (
            "const core=require(process.argv[1]);const s=JSON.parse(process.argv[2]);"
            "s.blockIndex=s.block_index;console.log(core.encodeState(s));"
        )
        done = subprocess.run([NODE, "-e", script, str(HERE / "lib" / "player" / "core.js"), json.dumps(self.STATE)], capture_output=True, timeout=60)
        self.assertEqual(done.stdout.decode().strip(), statetoken.encode_state(self.STATE))

    def test_fixture_worst_case_is_inside_the_scorm12_budget(self) -> None:
        report = statetoken.worst_case_report(fixture(), ["en", "hi"])
        self.assertTrue(report["fits_scorm12_precompress"])


class Translation(unittest.TestCase):
    def test_mock_keeps_human_text_and_pseudo_localises_the_rest(self) -> None:
        doc = fixture()
        res = validate(doc, preview=True)
        units = translate.collect_units(doc, res.i18n_pointers, "en")
        before_hi = doc["course"]["title"]["hi"]
        fill = translate.MockTranslator().fill(doc, units, ["en", "hi", "mr"])
        self.assertEqual(doc["course"]["title"]["hi"], before_hi)
        self.assertTrue(doc["blocks"][3]["points"][0]["hi"].startswith("[hi] "))  # b04 has no human Hindi
        self.assertTrue(doc["course"]["title"]["mr"].startswith("[mr] "))
        self.assertGreater(fill.kept["hi"], 0)
        self.assertEqual(doc["course"]["lang_states"]["hi"]["state"], "in_review")  # a human state is never overwritten
        self.assertEqual(doc["course"]["lang_states"]["mr"]["state"], "machine")

    def test_protected_terms_survive_pseudo_localisation(self) -> None:
        doc = fixture()
        res = validate(doc, preview=True)
        translate.MockTranslator().fill(doc, translate.collect_units(doc, res.i18n_pointers, "en"), ["en", "ta"])
        self.assertEqual(validate(doc, preview=True).rule_status["R07"], "pass")

    def test_live_path_refuses_without_every_gate(self) -> None:
        doc = fixture()
        res = validate(doc, preview=True)
        units = translate.collect_units(doc, res.i18n_pointers, "en")
        live = translate.LiveTranslator({}, mode="live", confirm=False, pii_findings=0)
        with self.assertRaises(translate.LiveRefused) as ctx:
            live.fill(doc, units, ["en", "ta"])
        message = str(ctx.exception)
        for fragment in ("ANTHROPIC_API_KEY", "PILOT_SPEND_CAP_INR", "--confirm"):
            self.assertIn(fragment, message)
        self.assertNotIn("[ta]", json.dumps(doc, ensure_ascii=False))  # nothing was written

    def test_live_path_refuses_when_estimate_exceeds_cap_or_pii_found(self) -> None:
        doc = fixture()
        res = validate(doc, preview=True)
        units = translate.collect_units(doc, res.i18n_pointers, "en")
        env = {"ANTHROPIC_API_KEY": "test-not-a-key", "PILOT_SPEND_CAP_INR": "0.01"}
        live = translate.LiveTranslator(env, mode="live", confirm=True, pii_findings=2)
        with self.assertRaises(translate.LiveRefused) as ctx:
            live.fill(doc, units, ["en", "hi", "ta", "ar"])
        self.assertIn("exceeds PILOT_SPEND_CAP_INR", str(ctx.exception))
        self.assertIn("PII scan reported 2", str(ctx.exception))

    def test_estimate_scales_with_languages_and_script(self) -> None:
        doc = fixture()
        res = validate(doc, preview=True)
        units = translate.collect_units(doc, res.i18n_pointers, "en")
        est = translate.LiveTranslator({}, mode="mock", confirm=False, pii_findings=0)
        one = est.estimate_cost(units, ["hi"], [], {}, "en")
        two = est.estimate_cost(units, ["hi", "ta"], [], {}, "en")
        self.assertAlmostEqual(two["inr"], 2 * one["inr"], delta=0.05)
        self.assertGreater(est.estimate_cost(units, ["hi"], [], {}, "en")["output_tokens"], est.estimate_cost(units, ["sw"], [], {}, "en")["output_tokens"])

    def test_parse_translations_is_strict(self) -> None:
        good = {"content": [{"type": "text", "text": json.dumps({"translations": [{"id": "/a", "text": "x"}]})}], "stop_reason": "end_turn"}
        self.assertEqual(translate.parse_translations(good, {"/a"}), {"/a": "x"})
        with self.assertRaises(HarnessError):
            translate.parse_translations(good, {"/a", "/b"})
        with self.assertRaises(HarnessError):
            translate.parse_translations({"content": [{"type": "text", "text": "not json"}], "stop_reason": "end_turn"}, {"/a"})
        with self.assertRaises(HarnessError):
            translate.parse_translations({**good, "stop_reason": "max_tokens"}, {"/a"})

    def test_confirming_translator_sends_nothing_without_a_typed_confirm(self) -> None:
        calls: list[str] = []

        class Probe(pipeline.ConfirmingTranslator):
            pass

        original = translate.LiveTranslator.send
        translate.LiveTranslator.send = lambda self, request: calls.append("sent") or {}  # type: ignore[method-assign]
        try:
            translator = Probe({"ANTHROPIC_API_KEY": "test-not-a-key"}, mode="live", confirm=True, pii_findings=0, prompt=lambda q: "yes")
            request = translator.build_request([translate.Unit("/a", "hello")], "en", "hi", [], {})
            with self.assertRaises(translate.LiveRefused):
                translator.send(request)
            self.assertEqual(calls, [])
        finally:
            translate.LiveTranslator.send = original  # type: ignore[method-assign]


class Speech(unittest.TestCase):
    def test_silent_mp3_has_the_requested_duration(self) -> None:
        data = tts.silent_mp3(5000)
        self.assertTrue(data.startswith(b"\xff\xfb"))
        self.assertGreaterEqual(tts.mp3_duration_ms(data), 5000)
        self.assertLess(tts.mp3_duration_ms(data), 5100)

    def test_cues_obey_webvtt_rules(self) -> None:
        text = "First sentence is here. Second sentence follows it closely. " * 6
        cues = tts.build_cues(text, tts.estimate_duration_ms(text))
        self.assertTrue(cues)
        previous_end = -1
        for cue in cues:
            self.assertGreaterEqual(cue.end_ms - cue.start_ms, 1000 - 1)
            self.assertLessEqual(cue.end_ms - cue.start_ms, 7000)
            self.assertGreater(cue.start_ms, previous_end)
            previous_end = cue.end_ms
        self.assertTrue(tts.cues_to_vtt(cues, "en").startswith("WEBVTT"))

    def test_devanagari_and_latin_durations_are_both_positive(self) -> None:
        self.assertGreater(tts.estimate_duration_ms("नमस्ते, आज हम विवादित लेन-देन के बारे में सीखेंगे।"), 2000 - 1)
        self.assertGreaterEqual(tts.estimate_duration_ms("Hi."), 2000)

    def test_narration_units_cover_slides_and_scenario_nodes(self) -> None:
        doc = fixture()
        units = tts.narration_units(doc, ["en"])
        keys = {u.key for u in units}
        self.assertIn("b01", keys)
        self.assertIn("s01_n1", keys)
        self.assertIn("s01_t_success", keys)


class AuditChain(unittest.TestCase):
    def test_chain_validates_and_detects_tampering(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "a.jsonl"
            log = audit.AuditLog(path, "r1", "tester", "mock")
            for i in range(4):
                log.record("event", i=i)
            ok, count, final = audit.verify_chain(path)
            self.assertTrue(ok)
            self.assertEqual((count, final), (4, log.chain_sha256))
            lines = path.read_text(encoding="utf-8").splitlines()
            lines[1] = lines[1].replace('"i": 1', '"i": 9').replace('"i":1', '"i":9')
            path.write_text("\n".join(lines) + "\n", encoding="utf-8")
            self.assertFalse(audit.verify_chain(path)[0])


def build_fixture_packages(root: Path):
    """Compile the fixture to all three standards (preview, en + hi + ar); shared by two test classes."""
    doc = fixture()
    res = validate(doc, preview=True)
    translate.MockTranslator().fill(doc, translate.collect_units(doc, res.i18n_pointers, "en"), ["en", "hi", "ar"])
    carried, _ = carried_languages(doc, ["en", "hi", "ar"], True)
    media: dict[str, bytes] = {}
    for unit in tts.narration_units(doc, carried):
        result = tts.MockTts().synthesize(unit)
        media[result.audio_path] = result.mp3
        media[result.vtt_path] = result.vtt.encode("utf-8")
        media[result.timestamps_path] = json.dumps(result.word_timestamps).encode("utf-8")
        pipeline._attach_media(doc, unit, result)
    pointers = validate(doc, preview=True, media_known=set(media)).i18n_pointers
    built = {}
    for std in STANDARDS:
        built[std] = compile_package(doc, pointers, std, carried, player_dir=HERE / "lib" / "player", media=media, out_path=root / f"{std}.zip", preview=True)
    return doc, media, carried, pointers, built


class Packaging(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.tmp = tempfile.TemporaryDirectory()
        cls.root = Path(cls.tmp.name)
        cls.doc, cls.media, cls.carried, cls.pointers, cls.built = build_fixture_packages(cls.root)

    @classmethod
    def tearDownClass(cls) -> None:
        cls.tmp.cleanup()

    def test_all_three_packages_validate(self) -> None:
        for std, built in self.built.items():
            report = validate_package(built.path, std, 70, self.doc, run_node=bool(NODE))
            failed = [c for c in report.checks if c.status == "fail"]
            self.assertFalse(failed, [c.as_dict() for c in failed])

    def test_builds_are_byte_stable(self) -> None:
        for std in STANDARDS:
            again = compile_package(self.doc, self.pointers, std, self.carried, player_dir=HERE / "lib" / "player", media=self.media, out_path=self.root / f"again-{std}.zip", preview=True)
            self.assertEqual(again.sha256, self.built[std].sha256, std)

    def test_manifest_contents(self) -> None:
        with zipfile.ZipFile(self.built["scorm12"].path) as z:
            names = z.namelist()
            self.assertEqual(names[0], "imsmanifest.xml")
            text = z.read("imsmanifest.xml").decode()
            self.assertIn("<adlcp:masteryscore>70</adlcp:masteryscore>", text)
            self.assertIn('adlcp:scormtype="sco"', text)
            self.assertIn("<schemaversion>1.2</schemaversion>", text)
            self.assertNotIn("runtime-scorm2004.js", names)
        with zipfile.ZipFile(self.built["scorm2004"].path) as z:
            text = z.read("imsmanifest.xml").decode()
            self.assertIn("2004 4th Edition", text)
            self.assertIn('adlcp:scormType="sco"', text)
            self.assertIn("<imsss:minNormalizedMeasure>0.70</imsss:minNormalizedMeasure>", text)
        with zipfile.ZipFile(self.built["cmi5"].path) as z:
            self.assertEqual(z.namelist()[0], "cmi5.xml")
            text = z.read("cmi5.xml").decode()
            self.assertIn('moveOn="CompletedAndPassed"', text)
            self.assertIn('masteryScore="0.70"', text)
            self.assertIn("<url>index.html</url>", text)

    def test_rtl_languages_get_rtl_direction_and_fonts_are_not_claimed(self) -> None:
        with zipfile.ZipFile(self.built["scorm12"].path) as z:
            data = json.loads(z.read("course-data.js").decode("ascii")[len("window.SENTIENTIA_COURSE = "):].rstrip().rstrip(";"))
        dirs = {l["tag"]: l["dir"] for l in data["langs"]}
        self.assertEqual(dirs, {"en": "ltr", "hi": "ltr", "ar": "rtl"})
        self.assertTrue(all(not n.startswith("fonts/") for n in zipfile.ZipFile(self.built["scorm12"].path).namelist()))

    def test_restricted_document_drops_uncarried_languages_but_keeps_ids(self) -> None:
        restricted = restrict_document(self.doc, self.pointers, ["hi"])
        title = restricted["course"]["title"]
        self.assertEqual(set(title), {"en", "hi"})  # base text stays as the fallback
        self.assertNotIn("ar", restricted["blocks"][0]["title"])
        self.assertEqual(restricted["course"]["langs"], ["hi"])

    def test_learner_builds_carry_only_approved_languages(self) -> None:
        doc = fixture()
        carried, excluded = carried_languages(doc, ["en", "hi", "mr"], preview=False)
        self.assertEqual(carried, ["en"])
        self.assertEqual(set(excluded), {"hi", "mr"})
        carried_preview, _ = carried_languages(doc, ["en", "hi", "mr"], preview=True)
        self.assertEqual(carried_preview, ["en", "hi", "mr"])

    def test_missing_media_is_a_hard_error(self) -> None:
        media = dict(self.media)
        media.pop(next(k for k in media if k.endswith(".mp3")))
        with self.assertRaises(HarnessError):
            compile_package(self.doc, self.pointers, "scorm12", self.carried, player_dir=HERE / "lib" / "player", media=media, out_path=self.root / "bad.zip", preview=True)

    def test_player_runtimes_do_not_pass_a_bare_setTimeout_as_a_method(self) -> None:
        # Regression: {setTimeout: setTimeout} throws "Illegal invocation" in browsers (found in the browser run).
        for path in (HERE / "lib" / "player").glob("runtime-*.js"):
            self.assertNotIn("setTimeout: setTimeout", path.read_text(encoding="utf-8"), path.name)

    def test_player_never_writes_html_from_strings(self) -> None:
        import re
        source = (HERE / "lib" / "player" / "player.js").read_text(encoding="utf-8")
        self.assertIsNone(re.search(r"\.innerHTML|insertAdjacentHTML|document\.write|eval\(|new Function", source))


class PackageValidationNegatives(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.tmp = tempfile.TemporaryDirectory()
        cls.root = Path(cls.tmp.name)
        built = build_fixture_packages(cls.root)[4]["scorm12"]
        with zipfile.ZipFile(built.path) as z:
            cls.files = {n: z.read(n) for n in z.namelist()}

    @classmethod
    def tearDownClass(cls) -> None:
        cls.tmp.cleanup()

    def rebuild(self, name: str, mutate) -> Path:
        files = dict(self.files)
        mutate(files)
        out = self.root / name
        write_zip(out, files, first=tuple(n for n in ("imsmanifest.xml",) if n in files))
        return out

    def failed_ids(self, path: Path) -> set[str]:
        return {c.id for c in validate_package(path, "scorm12", 70, None, run_node=False).checks if c.status == "fail"}

    def test_manifest_in_a_subfolder_fails_z02(self) -> None:
        def mutate(files):
            files["course/imsmanifest.xml"] = files.pop("imsmanifest.xml")
        self.assertIn("Z02", self.failed_ids(self.rebuild("sub.zip", mutate)))

    def test_doctype_in_the_manifest_is_refused(self) -> None:
        def mutate(files):
            files["imsmanifest.xml"] = b'<?xml version="1.0"?><!DOCTYPE m [<!ENTITY x "y">]>' + files["imsmanifest.xml"].split(b"?>", 1)[1]
        self.assertIn("Z03", self.failed_ids(self.rebuild("dtd.zip", mutate)))

    def test_wrong_scormtype_casing_fails_s05(self) -> None:
        def mutate(files):
            files["imsmanifest.xml"] = files["imsmanifest.xml"].replace(b"adlcp:scormtype=", b"adlcp:scormType=")
        self.assertIn("S05", self.failed_ids(self.rebuild("case.zip", mutate)))

    def test_wrong_mastery_score_fails_s06(self) -> None:
        def mutate(files):
            files["imsmanifest.xml"] = files["imsmanifest.xml"].replace(b"<adlcp:masteryscore>70<", b"<adlcp:masteryscore>80<")
        self.assertIn("S06", self.failed_ids(self.rebuild("mastery.zip", mutate)))

    def test_undeclared_and_missing_files_fail_z04_z05(self) -> None:
        def mutate(files):
            files["extra.txt"] = b"hello"
            files.pop("player.css")
        failed = self.failed_ids(self.rebuild("files.zip", mutate))
        self.assertTrue({"Z04", "Z05"} <= failed, failed)

    def test_missing_audio_fails_m01(self) -> None:
        def mutate(files):
            audio = next(n for n in files if n.endswith(".mp3"))
            files.pop(audio)
            files["imsmanifest.xml"] = files["imsmanifest.xml"].replace(f'<file href="{audio}"/>'.encode(), b"")
        self.assertIn("M01", self.failed_ids(self.rebuild("noaudio.zip", mutate)))

    def test_not_a_zip_fails_z01(self) -> None:
        bad = self.root / "bad.zip"
        bad.write_bytes(b"not a zip")
        self.assertIn("Z01", {c.id for c in validate_package(bad, "scorm12", 70, None, run_node=False).checks if c.status == "fail"})


class EndToEnd(unittest.TestCase):
    def run_pipeline(self, tmp: Path, **overrides):
        base = dict(course_path=COURSE, schema_path=SCHEMA, out_dir=tmp / "run", source_path=SOURCE, preview=True, smoke=bool(NODE), run_id="run-test")
        base.update(overrides)
        return pipeline.run(pipeline.RunConfig(**base))

    def test_mock_run_passes_and_writes_a_valid_audit_chain(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            result = self.run_pipeline(Path(tmp))
            self.assertTrue(result.ok, json.dumps(result.summary["package_reports"], indent=1)[:2000])
            self.assertFalse(result.summary["network_calls_made"])
            self.assertEqual(result.summary["spend_inr"], 0.0)
            self.assertEqual(len(result.packages), 3)
            ok, count, _ = audit.verify_chain(Path(tmp) / "run" / "audit" / "audit.jsonl")
            self.assertTrue(ok)
            self.assertGreaterEqual(count, 10)
            if NODE:
                self.assertTrue(result.summary["smoke"]["ok"], result.summary["smoke"])

    def test_per_language_mode_builds_one_package_per_language(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            result = self.run_pipeline(Path(tmp), package_mode="per_language", langs=["en", "hi", "ar"], standards=["scorm12"], smoke=False)
            self.assertTrue(result.ok)
            self.assertEqual(sorted(p.langs[0] for p in result.packages), ["ar", "en", "hi"])

    def test_learner_build_carries_only_the_approved_language(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            result = self.run_pipeline(Path(tmp), preview=False, standards=["scorm12"], smoke=False)
            self.assertTrue(result.ok)
            self.assertEqual(result.summary["carried_languages"], ["en"])
            self.assertIn("hi", result.summary["excluded_languages"])

    def test_pii_in_the_source_blocks_the_run_before_anything_is_built(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            dirty = Path(tmp) / "sop.md"
            dirty.write_text(SOURCE.read_text(encoding="utf-8") + "\nCustomer PAN ABCDE1234F called from 9876543210.\n", encoding="utf-8")
            with self.assertRaises(HarnessError) as ctx:
                self.run_pipeline(Path(tmp), source_path=dirty)
            self.assertIn("PII scan blocked", str(ctx.exception))
            self.assertFalse(list((Path(tmp) / "run").glob("**/*.zip")))
            findings = (Path(tmp) / "run" / "pii-findings.json").read_text(encoding="utf-8")
            self.assertNotIn("ABCDE1234F", findings)

    def test_live_mode_refuses_without_the_gates_and_writes_nothing(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            with self.assertRaises(translate.LiveRefused):
                self.run_pipeline(Path(tmp), mode="live", env={})
            self.assertFalse(list((Path(tmp) / "run").glob("**/*.zip")))

    def test_existing_audit_log_is_never_overwritten(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            self.run_pipeline(Path(tmp), standards=["scorm12"], smoke=False)
            with self.assertRaises(HarnessError):
                self.run_pipeline(Path(tmp), standards=["scorm12"], smoke=False)


if __name__ == "__main__":
    unittest.main()
