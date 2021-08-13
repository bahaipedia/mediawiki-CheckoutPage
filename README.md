**Extension:CheckoutPage** allows up to N readers to simultaneously access the same page from the library.

Important: this extension **doesn't restrict access by itself** (this is done by Extension:AccessControl). What CheckoutPage does is: it maintains access lists (pages like `Access:Name_of_page`), which contain the usernames of users who are allowed to access the page. These lists are then used by AccessControl.

## Installation

```
wfLoadExtension( "CheckoutPage" );
```

## Usage

If you add the the following wikitext to the page (e.g. `Some page`):
```
{{#checkout: access_page=Access:Some page|max_concurrent_users=2|checkout_days=5}}
```
... then any user who visits `Special:CheckoutPage/Some_page` will get their username added to the page `Access:Some_page`, but only if `Access:Some page` doesn't already have 2 other usernames listed.

Furthermore, if checkout was successful, then it will be remembered that in 5 days from now this username should be revoked from `Access:Some_page`. All revocations are performed by a maintenance script:
```
( cd /path/to/your/MediaWiki && php maintenance/runScript.php extensions/CheckoutPage/maintenance/revokeExpiredCheckouts.php )
```
... you can add this to `crontab` to run it daily (or hourly, or however often is convenient).

Upon successful checkout, the user is redirected back to the article (and can immediately read it).

Assuming you use Extension:AccessControl to restrict access to the page, you can add some wikitext like
```
Please visit [[Special:CheckoutPage/{{PAGENAME}}]] to get access to this page.
```
... into the MediaWiki error message that is shown when the user doesn't have access yet.

Alternatively, `{{#checkoutstatus:}}` can be used as error message. It will display either "Checkout" link (if the page is available) or "when will the page become available" (if too many users are already reading it).
