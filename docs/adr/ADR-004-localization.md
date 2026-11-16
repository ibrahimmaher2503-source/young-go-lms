# ADR-004: Locale resolution and public URLs

T-P1-01 implements D-011, ED-006 and ED-018. The configured default is Arabic.
English is the fallback for absent UI strings and JSON translations.

Public content routes use /ar/... and /en/... per ED-018. Declare the path
parameter as publicLocale and constrain it to ar/en. The path controls the
rendered locale without altering a stored preference, so URL content agrees
with canonicals and hreflang. P2 owns the public screens and SEO links.

Other web routes resolve the supported user preference, then session, encrypted
cookie, supported Accept-Language, and the configured default. POST /locale is
the CSRF-protected preference update. It saves an authenticated user's locale
and remembers the preference on the current device. Its 303 redirects to
the application root until localized product navigation exists.

Render the document with app()->currentLocale() for lang and
app()->isLocale('ar') ? 'rtl' : 'ltr' for dir. Use Spatie HasTranslations on
JSON columns. Normal attribute access selects the active locale and uses the
configured fallback. No translation tables or AI behavior are introduced.
