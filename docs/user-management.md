# User Management Specification

## Initial Roles

- `administrator`: manages users, roles, API keys, and system settings.
- `editor`: manages CMS content when content features are added.
- `viewer`: can view CMS content and profile information.
- `user`: can access only personal profile-level features.

## User Fields

- Email address.
- Display name.
- Profile picture (`avatar` — a path under `uploads/profilepics/`, nullable).
- Profile URL handle (`slug` — lowercase letters, numbers, dashes; unique). Used
  for the public profile at `/user/{slug}`. Auto-generated from the display name
  on account creation and backfilled for pre-existing accounts.
- Short bio (`bio` — up to 250 characters, nullable).
- Cover photo (`cover_photo` — a path under `uploads/coverpics/`, nullable).
- Email privacy (`hide_email` — boolean; hides the email on the public profile.
  Defaults to hidden; users opt in to show it).
- Social links (`social_github`, `social_linkedin`, `social_website` — nullable
  `http`/`https` URLs).
- Role.
- Active/inactive status.
- Password hash (nullable for OAuth-only accounts).
- Creation timestamp.
- Update timestamp.
- Last login timestamp.
- Linked OAuth identities (provider, provider user id, email, display name).

## Self-Service

Signed-in users can manage their own account on `/profile`:

- **Profile details** — short bio (≤250 characters), profile URL handle
  (`slug`, validated as 3–120 lowercase letters/numbers/dashes and unique),
  social links (GitHub, LinkedIn, Website — must be `http`/`https` URLs), and an
  email-privacy checkbox ("Hide email address from public profile", checked by
  default). Changes are recorded in the audit log as `profile.updated` (changed
  field names only; bio content and link URLs are not persisted to the log).
- **Cover photo** — upload a wide banner image (downscaled via GD to
  `app.cover_max_width`, default 1600, aspect ratio preserved) and stored under
  `public_html/uploads/coverpics/` with a random filename, mirroring the avatar
  security rules. Removing or replacing it deletes the previous file. Recorded in
  the audit log as `profile.cover_updated`.
- **Profile picture** — upload an image (JPEG/PNG/GIF/WebP); it is center-cropped
  to a square and resized via GD to `app.avatar_size`, then stored under
  `public_html/uploads/profilepics/`. Users can remove their picture. The avatar
  appears in the header and on the profile.
- **Change password** — requires the current password (for accounts that already
  have one). OAuth-only accounts (no password) can set one here to enable
  email/password login. New passwords must be at least
  `Auth::MIN_PASSWORD_LENGTH` (10) characters.
- **API keys** — visible only to roles with the `apikey.own` capability. See
  [permissions.md](permissions.md).

## Public profile

Profiles can identify as a creator or organization and choose public, unlisted,
or private visibility. Additional social links are stored as validated JSON and
rendered only for http(s) URLs. An organization can opt in to claim requests;
signed-in users submit a short request on the profile, and administrators review
it at `/admin/profile-claims`. Approval records the claimant and closes future
requests; identity verification remains a human operator responsibility.

Every active user has a public profile at `/user/{slug}` showing their avatar,
cover photo, display name, bio, social links, "Member since [date]", and a
paginated grid of the user's published articles. Compact system badges are
shown next to the name: "Administrator" for the administrator role, and
"Veteran contributor" when the account is over a year old (derived from
`role_name` and `created_at`; no extra schema). The email address is hidden by
default and shown only if the user has unchecked "Hide email address from public
profile" on `/profile` (`hide_email`). When the user has no published articles, a clean
text empty state is shown instead of the grid. The articles section is omitted
entirely when the `articles` feature toggle is off.

The slug is generated from the display name (transliterated to ASCII, lowercased,
dash-separated) and made unique by appending `-2`, `-3`, … Slugs are generated
in PHP (`includes/Slug.php`) because MySQL 5.7 cannot transliterate in pure SQL.

All user-supplied content rendered on the public profile passes through the
`e()` HTML-escaping helper; social links are additionally restricted to
`http`/`https` schemes (validated on save and re-checked before rendering) so a
`javascript:` URL can never become a link. The page adds no inline scripts and
complies with the site Content-Security-Policy.

## Self-Registration

Signup behavior is controlled by `app.signup_mode`:

| Mode         | Behavior                                                                 |
|--------------|--------------------------------------------------------------------------|
| `open`       | Anyone can sign up; the account is active immediately and the user is logged in. |
| `restricted` | Signup creates an **inactive** account held for admin approval. The user is told the account is pending and cannot sign in until an administrator activates it on `/admin/users`. |
| `invite`     | Signup requires a valid invite code. The account is active immediately. Codes are generated on `/admin/invites` (capability `user.manage`) and shown once. |
| `off`        | Signup is disabled entirely; the Sign up tab is hidden and `/auth/signup` returns 403. |

- New accounts are created with the `user` role.
- Passwords must be at least 10 characters.
- The Sign up tab and (in invite mode) the invite-code field appear in the auth modal automatically based on the mode.
- Administrators approve restricted signups by setting the user Active on `/admin/users`.

## Federated Login (Google / GitHub)

- Users can sign in with Google or GitHub from the auth modal.
- An OAuth identity is stored in `user_oauth_identities` and linked to a `users` row.
- On first OAuth sign-in:
  - If the provider identity is already linked, the user is logged in.
  - Else, if the provider returns a **verified** email that matches an existing
    local account, the identity is linked to that account and the user is logged
    in.
  - Else, if the provider returns a verified email, a new OAuth-only account
    (no password) is created, linked, and logged in.
  - Otherwise the sign-in is rejected with a "no verified email" message.
- OAuth-only accounts cannot log in with a password.
- See `docs/oauth-setup.md` for provider configuration.

### Security note on email linking

Auto-linking by verified email is convenient but assumes the provider is the
authority for that email address. Only verified emails are linked. If stricter
isolation is required, disable auto-linking and require a password login before
linking an identity.

## Administrator Features

Administrators (users with the `user.manage` capability) can:

- View users.
- Create users.
- Edit display name, role, and active status.
- Reset passwords.
- Deactivate accounts.
- View API key metadata.
- Revoke API keys.
- Revoke temporal sessions.

## Authorization

Access control is capability-based, not role-name-based. See `docs/permissions.md`
for the capability catalog and the default role grants. Use
`Auth::requireCapability('...')` to protect routes and `Auth::can('...')` for
conditional UI.

## Normal User Features

Users can:

- Log in.
- Log out.
- View profile information.
- Change their password when that feature is implemented.
- Create/revoke their own API keys when policy allows.

## Account Status

- Inactive users cannot log in.
- Existing sessions for inactive users should be rejected.
- Deactivation should revoke active sessions and API keys.

## Audit Expectations

Before production, add audit records for:

- Login success/failure.
- User creation.
- Role changes.
- Password resets.
- API key creation and revocation.
- Session revocation.
