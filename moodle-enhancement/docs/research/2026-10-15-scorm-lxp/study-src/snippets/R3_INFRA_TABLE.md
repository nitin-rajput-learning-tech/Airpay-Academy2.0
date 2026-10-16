| Option (India region) | List price (Oct 2026) | Marginal cost per course-language (10 min narration, autoregressive TTS + ASR QA pass) | Notes |
|---|---|---|---|
| AWS Mumbai g4dn.xlarge (T4) on-demand | $0.579/h [R3:79] | ≈ ₹11 | 24×7 would be ≈ ₹40k/month — not justified at pilot volume |
| AWS Mumbai g6.xlarge (L4) / g5.xlarge (A10G) | $0.966 / $1.208 per h [R3:80,81] | ≈ ₹9 / ₹11.5 | |
| Azure Central India NV6ads_A10_v5 | ≈ $0.636/h [R3:85] | ≈ ₹6 | fractional A10 |
| Google asia-south1 g2-standard-4 (L4) | $0.736/h [R3:86] | ≈ ₹7 | spot $0.293 |
| E2E Networks L4 / L40S (Indian cloud) | ₹50–70 / ₹120–150 per h, guide pricing (vendor marketing) [R3:91,92] | ≈ ₹5–7 / ₹8–10 (+18% GST) | cheapest credible option |
| Cyfuture L40S | ₹102/h on-demand, ₹50/h reserved (vendor marketing) [R3:95] | ≈ ₹7 / ₹3.5 | rate cards vary across the vendor's own pages |
| Yotta Shakti L40S / Tata Vayu 4×L40S | $2.30/GPU-h; ₹293/GPU-h (vendor marketing) [R3:93,94] | ≈ ₹15 / ₹20 | multi-GPU nodes, oversized for this |
| On-prem RTX 4090 workstation | ≈ ₹3.0–3.5 lakh capex (card ₹1.7–1.8 lakh ex-duty; listings swung to ₹3.5 lakh in 2026) [R3:97,98,103] | ≈ ₹71 each at 125/month, ≈ ₹9 at 1,000/month (amortisation) | GeForce driver EULA limits data-centre use (unverified); wins only above ~1,000 course-languages/month |
| CPU-only (AWS c6i.4xlarge, Class-A models only) | $0.68/h [R3:83] | ≈ ₹16–27 | Kokoro / FastPitch class only |

Scale check at 100 courses × 15 languages a year (1,500 course-languages): ≈ 150–300 GPU-hours ≈ **₹7.5–17k per year** on on-demand batch jobs [R3 Part C.4]. A standing GPU is needed only when live roleplay requires real-time ASR (Stage 3).
