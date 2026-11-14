# Historical MinSU Microsoft Entra ID Integration Checklist — Superseded

**Do not use this checklist for the current registration workflow.** The approved implementation uses native Laravel email/password credentials with an `@minsu.edu.ph` login address, separate personal Gmail OTP, and independent authorized account review. Microsoft Entra/OIDC is not required or exposed in the current registration routes. This document is retained only as historical technical work; its configuration steps must not be applied unless the project owner requests a separate future integration.

Status: preparation only. Public registration remains disabled until MinSU IT and the institutional privacy approver complete the external items below.

## Callback URI

- Callback route name: `microsoft.callback`
- Callback path: `/auth/microsoft/callback`
- Current local `APP_URL` fallback: `http://capstoneApp.test/auth/microsoft/callback`
- Production callback to register: `https://<MinSU-approved-MAGIREM-host>/auth/microsoft/callback`

The app uses `MICROSOFT_REDIRECT_URI` when set; otherwise Laravel generates the absolute callback from `APP_URL`. The current local `.test` HTTP URI is not an Entra production redirect URI. Entra requires HTTPS except for supported localhost development URIs. Register the exact scheme, host, port, and case-sensitive path, with no wildcard. Use a separate Entra app registration for development and production where practical.

Run the local RSA/JWKS/OIDC cryptographic tests with `powershell -ExecutionPolicy Bypass -File .\tests\run-microsoft-oidc-tests.ps1`. The script sets `OPENSSL_CONF` only in its own process, uses the checked-in test fixture, and restores the caller's prior process value; it does not change Windows-wide OpenSSL settings.

## Entra application settings for MinSU IT

1. Create an app registration in the official MinSU tenant, with supported account types set to **Accounts in this organizational directory only** (single tenant).
2. Add the production HTTPS callback under the **Web** platform. For a separately approved local test registration, add only its exact localhost/HTTPS callback.
3. Keep the flow as OpenID Connect authorization code with PKCE (`S256`), state, and nonce. The current Laravel client is confidential and expects a client secret at the token endpoint; certificate-based client assertions are not implemented in this code path.
4. Restrict the Enterprise Application with **Assignment required = Yes** and assign only institution-approved student and staff/counselor groups. A single-tenant registration can still include guest accounts; tenant ID alone is not a guarantee that an identity is an enrolled student or authorized staff member.
5. The app requests only `openid profile email`. It does not call Microsoft Graph. No Microsoft Graph delegated or application permission is required for the current sign-in flow. Review and remove any unnecessary default Graph permission such as `User.Read`, unless MinSU separately approves and the application is changed to use it.
6. MinSU IT should record the tenant (directory) ID and application (client) ID and provide the exact redirect URI. Configure the app's client secret through the approved secret store/local deployment environment, with expiry and rotation ownership recorded. Never send the secret by email/chat or commit it.
7. Confirm the tenant-specific issuer/discovery endpoints and app assignment policy in a non-production registration before enabling sign-in.

## Existing implementation security checks

The existing service uses tenant-specific authorization, token, discovery, and JWKS endpoints on `login.microsoftonline.com`; checks RS256 signatures against the tenant JWKS; validates issuer, audience, tenant ID, nonce, expiration and time claims; requires an OID GUID; and restricts the signed username/email claim to the configured institutional domain. The stable local identity key is the tenant ID plus object ID. Username/email claims are mutable and must not be treated as proof of enrollment or authorization; Entra assignment and the application's separate administrator approval remain required.

The authorization request creates random state, nonce, and a PKCE verifier/challenge. State is consumed once from the server session, is age-checked, and the code exchange uses the saved verifier. The callback is throttled. ID tokens and authorization codes are not stored as account credentials. Keep HTTPS, Laravel secure session cookies, accurate proxy/TLS configuration, and query-string redaction in deployment logs.

## Institutional privacy approvals required before registration opens

MinSU's designated privacy office must supply/approve these values and decisions; this checklist intentionally does not invent university policy:

- Approved privacy contact address: `____________________________`
- Approved privacy notice text/version for applicant profile and school/staff ID image capture: `____________________________`
- Approved retention period and deletion process for submitted ID images, verification metadata, and review/audit records: `____________________________`
- Approved purpose, access roles, storage location/security controls, and applicant consent wording: `____________________________`
- MinSU confirmation of canonical institutional email/UPN domains and which assigned groups may apply: `____________________________`
- Named MinSU reviewer for counselor institutional authorization: `____________________________`

The notice should accurately describe collection and use of profile details and ID images, who can review them, how long they are kept, how deletion is handled, and whom applicants can contact. No ID image is attached to Gmail notification messages or sent to MAGI/Flask/AI services by this flow.

## Application-side values and go-live gate

Configure only after approvals:

- `MICROSOFT_TENANT_ID`
- `MICROSOFT_CLIENT_ID`
- `MICROSOFT_CLIENT_SECRET` (deployment secret store; value never placed in this document)
- `MICROSOFT_REDIRECT_URI` (exact approved HTTPS callback)
- `MICROSOFT_INSTITUTIONAL_DOMAIN` (confirmed by MinSU IT)
- `ACCOUNT_VERIFICATION_PRIVACY_CONTACT`
- `ACCOUNT_VERIFICATION_PRIVACY_NOTICE_VERSION`
- `ACCOUNT_VERIFICATION_RETENTION_SCHEDULE`

Keep `MICROSOFT_REGISTRATION_ENABLED=false` until the Entra configuration, assignment restrictions, privacy fields, and real end-to-end tests are approved. Then test with MinSU-designated test accounts, verify student and counselor role requests separately, and confirm administrator review remains mandatory. Do not use a real student ID for setup testing without institutional authorization.

## Official Microsoft references

- [OpenID Connect on the Microsoft identity platform](https://learn.microsoft.com/en-us/entra/identity-platform/v2-protocols-oidc)
- [OAuth 2.0 authorization code flow and PKCE](https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow)
- [Single- and multitenant apps](https://learn.microsoft.com/en-us/entra/identity-platform/single-and-multi-tenant-apps)
- [ID-token claim validation](https://learn.microsoft.com/en-us/entra/identity-platform/claims-validation)
- [Restrict an app to assigned users](https://learn.microsoft.com/en-us/entra/identity-platform/howto-restrict-your-app-to-a-set-of-users)
- [Redirect URI requirements](https://learn.microsoft.com/en-us/entra/identity-platform/reply-url)
