# Enupal Translate Changelog

## 5.0.0 - 2026.09.16

### Added
- Content translation: entries, assets, categories and Commerce products can now be translated into other sites from the element index, including nested Matrix, Neo, Super Table and Content Block fields.
- Added OpenAI and Claude (Anthropic) as translation providers, for both static and content translation.
- Added a provider architecture: every provider now shares one interface, with a common base for LLM providers so OpenAI and Claude only implement their transport. Third-party providers can register through `Providers::EVENT_REGISTER_PROVIDERS`.
- Added field serializers for Plain Text, Table, Link, Content Block and rich text (CKEditor, Redactor, TinyMCE), plus dedicated handling for Vizy, Hyper, Linkit, SEOmatic and Ether SEO — 16 field types in total.
- Added an `enupal-translate/translate/fields` console command reporting which fields in an install can be translated, and why any are skipped. Third-party fields can register through `Content::EVENT_REGISTER_SERIALIZERS`.
- The dashboard opens on the last 30 days rather than an empty range, and the date inputs show it.
- The dashboard's recent activity table shows 10 rows per page, with a pager that keeps the active filters.
- Added a dashboard reporting translation counts, token usage, API calls and failures, with filters by date, provider, type and target language, and a button to purge metrics.
- The default content translation provider is chosen under Settings → Providers. The sidebar pre-selects it on every entry, and its dropdown swaps provider for a one-off translation without changing the default.
- Added an Enupal Translate panel to the sidebar of entry, asset, category and product edit screens, for translating the element you're looking at into one site or all of them. It also explains itself when no provider is configured yet, so the feature is discoverable.
- Added queue jobs for content translation and for large static translation batches.
- Added shared AI settings for protected terms and tone of voice.
- Added an `enupal-translate:translateContent` permission.
- Added `enupal-translate/translate/providers` and `enupal-translate/translate/test` console commands for checking provider configuration.

### Changed
- Settings are now split across General, Providers and Content pages. Existing Yandex and Google credentials are carried over untouched; they have simply moved to the Providers page.
- When no default content provider has been chosen, the most capable enabled provider is used rather than whichever was registered first — so a site with both a Google Cloud key and the free endpoint enabled no longer falls back to the scraper.
- Bulk static translation actions are now generated from whichever providers are enabled, rather than being hard-coded.
- Batches are de-duplicated before being sent, so a string that repeats is only translated (and billed) once.
- Failed provider requests are now retried with exponential backoff on rate limits and server errors.

### Fixed
- Fixed a 500 error when applying the dashboard filters. Craft's date fields post an array of date/locale/timezone parts rather than a string, which was being passed straight into a `DateTime` constructor.
- Fixed dashboard dates shifting back a day. A date typed into the control panel means that day in the site's timezone, but Craft reads a bare date as UTC unless told otherwise, so any site at a negative offset saw the range move.
- Fixed saving an entry triggering a translation. The sidebar panel rendered a `<form>` inside Craft's own entry form; nested forms are invalid HTML, so the browser hoisted its hidden inputs into the outer form and Save submitted the translate action instead of saving the entry. The panel now posts over AJAX and contains no form or named inputs.
- Fixed the translated draft being reported as the live entry. `saveTarget()` swapped in the newly created draft locally, so callers were handed the untouched canonical element — the success message said "translated" rather than "saved as a draft", and the link went to the live entry showing the old content.
- The success message now says whether the result was saved as a draft, at what time, and links straight to it.
- Fixed a `TypeError` on the entry, asset and category indexes: the bulk action was registered against `RegisterComponentTypesEvent` instead of `RegisterElementActionsEvent`.
- Provider errors now report the API's own message instead of Guzzle's full HTTP dump.
- A 429 caused by an exhausted quota is no longer retried, since waiting cannot resolve it.
- Google Translate (Free) requests are now paced, and a rate limit is no longer retried — retrying a throttled scrape only deepens the block. Whatever was translated before the block is kept rather than discarded, and the error explains that the free endpoint is scraped rather than an official API.
- Partial results from any provider are now kept when a batch fails partway, instead of being thrown away and paid for again.
- The sidebar warns when the entry has unsaved changes, since translation reads the saved version.
- Fixed Google Translate (Free) mis-aligning results: strings were joined with ` || ` and split apart again, which silently attributed translations to the wrong source string whenever the separator did not survive translation. Yandex and Google Cloud now use their native batch APIs, and each free-Google string is sent on its own request.
- Fixed deprecation notices on PHP 8.4 from implicitly nullable parameters.

## 4.1.2 - 2025.07.23

### Fixed
- Fixed error generating empty translations when saving ([#62])

[#62]: https://github.com/enupal/translate/issues/62

## 4.1.1 - 2025.01.09

### Fixed
- Fixed error when only one site introduced on Craft CMS v5.5.4 ([#74])

[#74]: https://github.com/enupal/translate/issues/74

## 4.1.0 - 2025.01.07

### Fixed
- Fixed `Variable "elementInstance" does not exist` error introduced on Craft CMS v5.5.4 ([#74])

[#74]: https://github.com/enupal/translate/issues/74

## 4.0.3 - 2024.08.26

### Fixed
- Fixed the issue where the source column was not shown on the index page ([#71])

[#71]: https://github.com/enupal/translate/issues/71

## 4.0.2 - 2024.06.22

### Fixed
- Fixed issue when having one site ([#68])

[#68]: https://github.com/enupal/translate/issues/68

## 4.0.1 - 2024.03.31

### Updated
- Updates changelog

## 4.0.0 - 2024.03.29

### Added
- Added Craft CMS 5 support

## 3.1.0 - 2023.08.30

### Added
- Adds support to the `enupal-translate/translate/sync` craft command to sync translations from DB ([#61])

### Fixed
- Fixed issue on Craft 4.5 ([#64])

[#64]: https://github.com/enupal/translate/issues/64
[#61]: https://github.com/enupal/translate/issues/61

## 3.0.1 - 2022.07.12

### Fixed
- Fixed issue on mysql ([#56])
- Fixed issue where there is only one site on Craft CMS ([#55])

[#55]: https://github.com/enupal/translate/issues/55
[#56]: https://github.com/enupal/translate/issues/56

## 3.0.0 - 2022.05.20

### Added
- Added Craft CMS 4 support

## 2.3.0 - 2022.01.12

### Fixed
- Fixed guzzle error ([#43])

[#43]: https://github.com/enupal/translate/issues/43

## 2.2.1 - 2021.04.14

### Fixed
- Revert mysql change ([#41])
  
[#41]: https://github.com/enupal/translate/issues/41

## 2.2.0 - 2021.04.10

### Fixed
- Fixed issue on mysql 8 ([#41])

### Updated
- Updated requirement `craftcms/cms ^3.6.0` ([#43])
- Updated requirement `stichoza/google-translate-php" 4.1.4`

[#43]: https://github.com/enupal/translate/issues/43
[#41]: https://github.com/enupal/translate/issues/41

## 2.0.0 - 2020.11.16

> {tip} The most requested feature is here, Sync your static translations into your database without no extra queries that may impact your page load time. [docs](https://docs.enupal.com/translate/translate/sync-with-db.html). Enjoy!

### Added
- Added support to Sync translations with Database. ([#34]) 

[#34]: https://github.com/enupal/translate/issues/34

## 1.3.1 - 2020.10.26
### Fixed
- Fixes issue with composer v2

## 1.3.0 - 2020.06.29
### Fixed
- Fixed issue with Yandex lib

## 1.2.2 - 2020.04.19
### Added
- Added `guzzlehttp/guzzle ^6.3.0` ([#30]) 

[#30]: https://github.com/enupal/translate/issues/30

## 1.2.1 - 2020.03.23
### Fixed
- Fixed issue on "Google Translate Free" ([#28]) 

[#28]: https://github.com/enupal/translate/issues/28

## 1.2.0 - 2019.01.27
### Added
- Added Craft 3.1 requirement
- Added twig search method under general Settings
- Added regex search API
- Added twig search Legacy method 
- Added twig search optimized method 
- Added php search method 
- Added js search method 

## 1.1.8 - 2018.12.22
### Fixed
- Fixed issue on Craft 3.1

## 1.1.7 - 2018.12.18
### Added
- Added support for import csv files

## 1.1.6 - 2018.11.20
### Added
- Added support for "CMD + S" shortcut

### Fixed
- Fixed issue when only one site available to translate in dropdown was not saving 

## 1.1.5 - 2018.10.24
### Fixed
- Fixed issue with regional codes when using Google Cloud Translate
- Fixed `Argument 1 passed to craft\services\TemplateCaches::includeElementInTemplateCaches() must be of the type integer, string given` error 

## 1.1.4 - 2018.10.12
### Improved
-Improved folder location of assets

### Fixed
- Fixed bug when using Google Translate API

## 1.1.3 - 2018.09.12
### Added
- Added plugin name override setting

## 1.1.2 - 2018.07.07
### Fixed
- Fixes issue with Yandex when using lang by region in the Site settings. #4
- Fixed bug when selecting a template folder source

## 1.1.1 - 2018.07.05
### Fixed
- Fixed bug when selecting a template source

## 1.1.0 - 2018.07.02
### Improved
- Improved template sources

## 1.0.5 - 2018.04.03
### Improved
- Improved code inspections

## 1.0.4 - 2018.03.14
### Fixed
- Fixed composer error

## 1.0.3 - 2018.03.02
### Fixed
- Fixed composer error

## 1.0.2 - 2018.03.02
### Improved
- Improved UI labels

## 1.0.1 - 2018.02.25
### Improved
- Improved code conventions

## 1.0.0 - 2018.02.24
### Added
- Initial release
