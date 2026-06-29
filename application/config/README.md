# Configuration — local secrets

Some config files hold real credentials (DB passwords, API keys) and are **not**
committed. They are gitignored so secrets never end up in the repository.

For each one, copy the `.example` template and fill in your own values:

| Real file (gitignored)                         | Template to copy                                       | Holds                                  |
|------------------------------------------------|--------------------------------------------------------|----------------------------------------|
| `application/config/Database.php`              | `application/config/Database.php.example`              | MySQL hosts / users / passwords        |
| `application/config/twilio.php`                | `application/config/twilio.php.example`                | Twilio SID, auth token, Verify SID     |
| `application/modules/store/config/sumup.php`   | `application/modules/store/config/sumup.php.example`   | SumUp merchant code + secret API key   |

## Setup

```bash
cp application/config/Database.php.example            application/config/Database.php
cp application/config/twilio.php.example              application/config/twilio.php
cp application/modules/store/config/sumup.php.example application/modules/store/config/sumup.php
```

Then edit each copy and set your real values.

## Notes

- **Database.php** — two connection groups: `cms` (the FusionCMS website DB) and
  `account` (the emulator `auth`/bnet DB).
- **twilio.php** — set `twilio_enabled = true` to require an SMS code on
  registration; `twilio_login_2fa = true` to also require it as login 2FA.
- **sumup.php** — authenticate with either the secret API key (`sup_sk_...`) or
  OAuth2 client credentials. Leave the unused option empty.

Never commit the real files. If a secret is ever pushed by mistake, rotate it
immediately — git history keeps it even after deletion.
