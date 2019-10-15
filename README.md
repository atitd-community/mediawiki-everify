This is a MediaWiki extension that adds a "verification system" to help track changes between Wikis/tales in A Tale in the Desert.

Installation
============

  * Download and place the files in a directory called `EVerify` in your `extensions/` folder.
  * Add the following code at the bottom of your `LocalSettings.php`:

```php
wfLoadExtension( 'EVerify' );
```

  * **Done** — Navigate to `Special:Version` on your wiki to verify that the extension is successfully installed.

Usage and How It Works
======================

Simple Version
--------------

Two new tags have been created to mark information as "verified" or
"unverified" in a way that is similar to how previous Wikis handled
things. To mark a piece of information as "verified", simply add a
`<v />` tag next to it. Similarly, to mark a piece of information as
"unverified", simply add a `<uv />` tag next to it. Every page will
attempt to estimate how thoroughly verified the entire page is based on
the usage of these tags throughout so you can get a rough idea of the
quality of any given page.

Detailed Version
----------------

As mentioned above, most tagging can be accomplished simply with the
`<v />` and `<uv />` tags. However, there are a few more advanced
features that can be used by power users.

#### Highlighting

Firstly, the `<v />` and `<uv />` tags can be *wrapped around* text in
order to highlight a chunk of text in the event that a simple icon
doesn't mark a piece of information clearly enough. An example would be
to mark a sentence as "verified" by replacing the simple `<v />` tag by
instead writing `<v>This text is verified</v>`.

#### Weighting

If you really want to micromanage the verification percentage displayed
in the top right of the page, you can "weight" `<v />` and `<uv />` tags
based on how much information they cover. For example, if a single piece
of information covers 4 times as much information as other tags on the
page, you could flag that information `<v w="4" />` which will weight it
appropriately when calculating the page's verification percentage.

#### Fully Verified Pages

If you want to flag a page as being fully verified but don't see the
need to pepper in `<v />` icons throughout the page, you can instead add
a `<fully-verified />` or `<all-verified />` tag anywhere on the page.
This will override any other verification tags on the page and mark the
page as "Fully Verified".

#### Disabling/Ignoring Verification

If you want to disable the display of the verification box for a given
page, such as on the Main Page, you can add a `<disable-verification />`
or `<no-verification />` tag anywhere on the page to ignore all other
verification tags on the page.