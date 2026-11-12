# Payment provider eligibility for a 17-year-old in Germany

Research checked 12 November 2026 against the providers' published terms and support documentation. This records provider eligibility only; it does not decide legal capacity, tax treatment, school approval, or whether a specific sponsorship arrangement is suitable.

| Provider | Published age rule | Guardian requirements / practical consequence |
| --- | --- | --- |
| GitHub Sponsors | People over 13 and under 18 may give and receive Sponsor funds. | GitHub's additional terms require that Stripe can collect verification information from the legal guardian. GitHub does not specify the full verification checklist in those terms. Do not enable a public link until onboarding confirms the account and guardian. |
| Stripe | Accounts may be created from age 13. | Before the account accepts charges or receives payouts, a legal guardian must become the account owner. Stripe says it requests the guardian's name, date of birth, postal address and consent; it may request ID if it cannot verify automatically. Both the minor and guardian are bound by the agreement. Confirm the current German onboarding flow directly before use. |
| PayPal Germany | An individual must be at least 18 and have full legal capacity. | The German user agreement does not describe a guardian exception for a minor's personal account. Do not use an adult's account as a workaround or advertise PayPal as an available channel. |
| Buy Me a Coffee | The account holder must be at least 18 or old enough to enter a binding contract. | No guardian pathway for a 17-year-old is stated in its terms. Do not enable it. |

## Implementation decision

- No live checkout, payment form, bank details, or direct payment collection is implemented.
- GitHub Sponsors is shown only when `SUPPORT_GITHUB_SPONSORS_URL` is configured. Keep it unset until eligibility and guardian verification are complete.
- PayPal and Buy Me a Coffee are not linked.
- Any future provider activation must verify the provider's then-current German onboarding requirements and an adult guardian's role. School/team funds must use the approved school's or team's own authorized account and process, separate from personal and venture funds.
- A payment provider's age eligibility does not establish tax deductibility, donation-receipt eligibility, or the legal classification of a payment.

## Official sources

- [GitHub Sponsors Additional Terms, section 1.1](https://docs.github.com/en/site-policy/github-terms/github-sponsors-additional-terms)
- [Stripe Services Agreement, age restrictions](https://stripe.com/legal/ssa)
- [Stripe support: age requirement to create an account](https://support.stripe.com/questions/age-requirement-to-create-a-stripe-account?locale=de-DE)
- [PayPal Germany User Agreement](https://www.paypal.com/de/legalhub/paypal/useragreement-full?locale.x=en)
- [Buy Me a Coffee Terms of Use](https://buymeacoffee.com/terms)
