# R6 — Learning-science check of the report's pedagogical claims

*Prepared 2026-10-15 from web lookups (standard search; abstracts, publisher pages and reputable secondary summaries). Where a number could not be confirmed in the primary text it is marked **unverified**; such numbers must not be quoted in marketing.*

## Verdict table

| # | Claim in the report | Verdict | Key numbers | Best sources |
|---|---|---|---|---|
| 1 | "Learners forget 50% within 1 hour, 70% within 24 hours, 80% within 30 days" (Ebbinghaus) | **Contested / folklore** | Ebbinghaus (1885) and the 2015 replication measured *savings in relearning time*, not percentage recalled. Replication savings reported by secondary summaries: ≈ 47% at 20 min, ≈ 32% at 1 day, ≈ 4% at 31 days (**unverified against the paper's tables**); the curve is for nonsense syllables learned by one subject, not workplace knowledge. The popular 50/70/80 figures are conversions and extrapolations, not reported results | Murre & Dros 2015, PLOS ONE 10(7):e0120644, doi:10.1371/journal.pone.0120644 [1][2]; textbook discussion [3] |
| 2 | Spaced repetition improves long-term retention | **Supported** | Distributed practice beats massed practice across 317 experiments/839 assessments; the optimal inter-study interval grows with the retention interval (for retention of weeks to months, gaps of days to weeks); expanding schedules are reasonable | Cepeda, Pashler, Vul, Wixted & Rohrer 2006, Psychological Bulletin 132(3):354–380 [4][5] |
| 3 | Retrieval practice ("testing effect") beats re-study | **Supported** | Practice tests outperform restudy and other conditions, g ≈ 0.61 across 272 effect sizes / 118 articles / 15,427 participants; multiple-choice formats and feedback (showing the correct answer) strengthen the effect; format match between practice and final test helps | Adesope, Trevisan & Sundararajan 2017, Review of Educational Research 87(3):659–701, doi:10.3102/0034654316689306 [6][7]; Roediger & Karpicke 2006 (original testing-effect studies; not re-fetched today) |
| 4 | Branching scenarios / simulations improve learning and transfer versus linear instruction | **Partially supported** | Simulation games vs comparison groups (k = 65, N = 6,476): declarative knowledge +11%, procedural +14%, retention +9%, self-efficacy +20% (figures from abstract reproductions, **verify in the journal**); strong evidence of publication bias; gains depend on active instruction and design | Sitzmann 2011, Personnel Psychology 64(2):489–528 (abstract reproduced in [8][9]) |
| 5 | Live AI roleplay with LLM-as-judge scoring is reliable enough for assessment | **Partially / with safeguards** | Strong LLM judges can approach human-level agreement on chatbot preference tasks, but the same work documents position bias, verbosity bias, self-enhancement bias and limited reasoning; the headline agreement figure (often quoted as "> 80%") is **unverified from the full text today**. No peer-reviewed evidence found for roleplay *training* scoring in sales/customer service with human inter-rater comparison | Zheng et al. 2023, "Judging LLM-as-a-Judge with MT-Bench and Chatbot Arena", NeurIPS 2023 Datasets & Benchmarks [10][11] |
| 6 | Narrated slides with on-screen bullets are effective | **Supported with a boundary** | Modality principle (graphics + narration beats graphics + on-screen text): median d ≈ 0.65 across a 76-study review; redundancy principle (adding on-screen text that duplicates narration hurts): supported in 10/10 tests, median d ≈ 0.69; overall multimedia-principle meta-analysis g ≈ 0.37 with heterogeneity and publication-bias caveats; effects shrink when the lesson is self-paced, simple, or learners have prior knowledge | Mayer, Multimedia Learning (modality chapter) [12]; Cambridge Handbook of Multimedia Learning, extraneous-processing principles chapter [13]; overview [14] |
| 7 | Microlearning (5–10 minute modules) is effective | **Partially supported** | Reviews report positive effects attributed to short, single-topic content, but study quality is mixed and the evidence base is small; Taylor & Hung 2022 is cited by later reviews as a scoping review (primary text **not retrieved today**) | 2024–2025 systematic reviews [15][16] |
| 8 | Machine translation + human review (post-editing) is faster than translation from scratch at equal or better quality | **Supported, magnitude varies** | Controlled study: post-editing reduced time and improved quality for EN→AR/FR/DE; realistic-workbench study: significant but smaller savings than lab studies; industry commonly cites ≈ 40% time savings, with no agreement on the figure and strong dependence on text type and MT quality | Green, Heer & Manning 2013, CHI, doi:10.1145/2470654.2470718 [17]; Läubli et al. 2013, MT Summit WPTP [18]; overview [19][20] |
| 9 | 70% mastery threshold | **Convention, not evidence** | No source found that validates 70 specifically; mastery-learning traditions use higher criteria (80–90%) for prerequisite skills; thresholds should follow consequence of error, not habit (**no primary source fetched today**) | — |
| 10 | Learning in one's first language improves comprehension for adult workplace learners | **Plausible, thin evidence** | Most evidence is from children's schooling; adult workplace studies are qualitative or small (South Africa adult education; Singapore workplace literacy project recommending bilingual pedagogy); no meta-analysis with comprehension outcomes found | [21][22][23] |

## Design implications for the pipeline and the reinforcement engine

1. Build reinforcement as **retrieval practice on an expanding schedule** (defaults 2/7/30 days, per-customer configurable), using questions from the course pool with feedback — the two effects with the strongest evidence (claims 2 and 3).
2. Market it as **"spaced retrieval practice"**; never print retention percentages or "defeat the forgetting curve".
3. Generate **narration that does not read the bullets verbatim**; keep bullets short (≤ 5 × ≤ 8 words); make captions optional and off by default for narrated slides where the learner can read (claim 6), on by default for accessibility settings.
4. Keep **scenarios short and consequence-rich** (immediate feedback per choice); measure transfer, not completion; expect modest, design-dependent gains (claim 4).
5. **Live roleplay scores are formative until calibrated**: hidden rubric, position-randomised judging, human spot checks, inter-rater agreement target κ ≥ 0.6 before any score counts for compliance (claim 5).
6. **Mastery thresholds are a customer setting** with guidance by consequence of error (70 as the Airpay default is a convention; higher for safety-critical procedures).
7. **Post-edit, do not re-translate**: plan reviewer time on post-editing rates, keep the TM growing, and measure the real time saving per language rather than assuming 40%.
8. **Offer the first language, measure the effect**: ship Hindi/regional versions and compare quiz scores and completion between languages on the same course — Sentientia can produce the evidence the literature lacks for adult workplace learners in India.

## Honest marketing language

| Instead of | Say |
|---|---|
| "Learners forget 80% in 30 days" | "Without practice, knowledge fades; Sentientia schedules short retrieval practice at 2, 7 and 30 days" |
| "Simulations double learning" | "Decision scenarios with immediate consequences, shown in research to improve knowledge and confidence more than lectures" |
| "AI scores your roleplay" | "AI gives structured feedback on practice conversations; scores count only after human calibration" |
| "Courses in minutes" | "A reviewed first draft in hours; approved multilingual courses in days" (quote measured pilot times) |

## Sources (accessed 2026-10-15)

1. Murre JMJ, Dros J (2015). Replication and Analysis of Ebbinghaus' Forgetting Curve. PLOS ONE 10(7): e0120644. https://journals.plos.org/plosone/article?id=10.1371/journal.pone.0120644
2. PMC copy: https://www.ncbi.nlm.nih.gov/pmc/articles/PMC4492928/
3. Crump M. Cognition textbook, chapter 8 (Memory I), discussion of Ebbinghaus savings: https://crumplab.com/cognition/textbook/C8_Memory_I.html
4. Cepeda NJ, Pashler H, Vul E, Wixted JT, Rohrer D (2006). Distributed practice in verbal recall tasks. Psychological Bulletin 132(3):354–380. https://pubmed.ncbi.nlm.nih.gov/16719566/
5. Author page / PDF: https://www.yorku.ca/ncepeda/publications/CPVWR2006.html
6. Adesope OO, Trevisan DA, Sundararajan N (2017). Rethinking the use of tests: a meta-analysis of practice testing. Review of Educational Research 87(3):659–701. https://journals.sagepub.com/doi/10.3102/0034654316689306 (institutional record: https://rex.libraries.wsu.edu/esploro/outputs/journalArticle/Rethinking-the-Use-of-Tests-A/99900601154801842)
7. Secondary summary of effect sizes (Frontiers in Psychology 2018): https://www.frontiersin.org/articles/10.3389/fpsyg.2018.02412/full
8. Abstract reproduction of Sitzmann 2011 (L-TEN): https://l-ten.org/wp-content/uploads/2022/08/Games_doc.pdf
9. Kapp K. Abstracts of three meta-analysis studies of serious games: https://karlkapp.com/?p=4333
10. Zheng L et al. (2023). Judging LLM-as-a-Judge with MT-Bench and Chatbot Arena. NeurIPS 2023 Datasets and Benchmarks. https://proceedings.neurips.cc/paper_files/paper/2023/hash/91f18a1287b398d378ef22505bf41832-Abstract.html
11. arXiv 2306.05685 listing: https://papers.pytorch.kr/papers/2306.05685
12. Mayer RE. Multimedia Learning, modality principle chapter (Cambridge): https://www.cambridge.org/core/books/abs/multimedia-learning/modality-principle/E5CD6E01CEA0B568CE260F66A3CD0D1F
13. Cambridge Handbook of Multimedia Learning — principles for reducing extraneous processing (coherence, signaling, redundancy, contiguity): https://www.cambridge.org/core/books/abs/cambridge-handbook-of-multimedia-learning/principles-for-reducing-extraneous-processing-in-multimedia-learning-coherence-signaling-redundancy-spatial-contiguity-and-temporal-contiguity-principles/C98AB3A6CE760DD63C048936EA0B3B44
14. Modality and multimedia overview (ACU Inspire): https://inspire.acu.edu.au/articles/modality-and-multimedia
15. Microlearning beyond boundaries: a systematic review (2024, PMC): https://www.ncbi.nlm.nih.gov/pmc/articles/PMC11774797/
16. Microlearning and self-regulated learning: systematic literature review (2025): https://journal.unj.ac.id/unj/index.php/ishel/article/view/70235
17. Green S, Heer J, Manning CD (2013). The efficacy of human post-editing for language translation. CHI 2013. https://idl.uw.edu/papers/post-editing
18. Läubli S et al. (2013). Assessing post-editing efficiency in a realistic translation environment. MT Summit WPTP. https://aclweb.org/anthology/2013.mtsummit-wptp.10.pdf
19. Wikipedia, Postediting (industry time-saving claims and the lack of consensus): https://en.wikipedia.org/wiki/Postediting
20. TAUS, Translation productivity revisited: https://www.taus.net/resources/blog/translation-productivity-revisited
21. Singapore IAL workplace literacy project summary (bilingual pedagogy recommendation): https://ial.edu.sg/getmedia/036013fd-e9f2-40e0-a9c0-c20871ceab63/GA17-06_Project-Summary-for-IAL-Website_updated.pdf?ext=.pdf
22. Mother-tongue instruction in South African adult education (AJOL): https://ajol.info/index.php/gab/article/view/203346
23. CAL brief, workplace ESL literature: https://www.cal.org/caela/esl_resources/briefs/work.html
