# SOP-CS-014 — Handling a disputed UPI transaction (Customer Support)

**Owner:** Customer Support Operations · **Version:** 3.1 · **Effective:** 1 September 2026 · **Review cycle:** annual
**Applies to:** all Tier-1 and Tier-2 support agents handling merchant and consumer UPI disputes.

> Synthetic training fixture written for the Sentientia content-pipeline pilot. It contains no real customer, employee, account or transaction data. Any resemblance to a real case is coincidental.

## 1. Purpose

This procedure tells a support agent exactly what to do when a customer reports that a UPI payment was debited but the merchant did not receive it, or that a payment they did not authorise appears on their statement. Following it protects the customer, keeps Airpay inside the RBI turnaround times for failed transactions, and produces the evidence the disputes team needs.

## 2. Definitions

- **Failed transaction:** the customer's account was debited but the beneficiary was not credited.
- **Unauthorised transaction:** a debit the customer says they did not make or approve.
- **TAT:** turnaround time, measured in calendar days from the transaction date.
- **UTR / RRN:** the 12-digit transaction reference the customer sees in their banking app.

## 3. Procedure

### Step 1 — Verify the caller and the transaction

1. Authenticate the caller using the registered mobile number and two security questions. Never ask for the UPI PIN, the OTP, the full card number or the CVV. If the caller volunteers any of these, stop them politely and record that the information was offered but not captured.
2. Collect the UTR/RRN, the transaction date, the amount and the merchant name exactly as shown in the customer's app.
3. Look up the transaction in the disputes console. Confirm whether the status is Success, Pending or Failed.

### Step 2 — Classify the case

- **Pending within 3 working days:** explain that pending UPI payments auto-reverse or settle within the regulatory window. Share the expected date. Do not raise a dispute yet.
- **Failed and not reversed after the window:** raise a Failed Transaction dispute with the UTR attached.
- **Unauthorised:** treat as a fraud report. Block further UPI debits on the registered number immediately, then raise an Unauthorised Transaction dispute. Advise the customer to also report to their bank and to the national cyber-crime helpline.
- **Merchant service dispute (goods not delivered, wrong item):** this is not a payment failure. Route to the merchant-grievance process and explain the difference to the customer.

### Step 3 — Set expectations

Tell the customer the reference number, the TAT for their case type, and how they will be informed. Promise only what the console shows. Never say "this will be reversed today" unless the console status already says Reversed.

### Step 4 — Record and escalate

1. Write the case note in the required format: classification, UTR, amount, customer statement in their own words, actions taken, commitments made.
2. Escalate to Tier-2 when: the amount exceeds ₹50,000, the customer reports more than one unauthorised debit, the customer mentions a lawyer, the media or a regulator, or the customer is distressed and asks for a supervisor.
3. Never close a case while a commitment to the customer is open.

## 4. Behaviour standards

Acknowledge the problem in the first sentence. Use the customer's name. Do not blame the customer, the bank or the merchant. If the customer is angry, lower your pace and summarise what you have heard before asking the next question. Switch to the customer's preferred language when you can; use the interpreter line when you cannot.

## 5. Common mistakes to avoid

- Asking for the UPI PIN or OTP "to verify" — this is a disciplinary offence.
- Raising a dispute for a transaction that is still Pending.
- Promising a date that the console does not show.
- Closing a case with an open promise.
- Treating a merchant service dispute as a payment failure.

## 6. Mastery

Agents must complete this module with a score of at least 70% and repeat it annually. Supervisors review two recorded calls per agent per quarter against this procedure.
