EEvent Helper is an ExpressionEngine extension that makes managing events channels and expiration dates painless.

Once activiated, when entries are published in any of your specified "events" channels, their *Expiration Date* will be automatically set to 11:59 PM on the day of the event. Or, if you choose a custom date field to serve as an *End Date* indicator, their *Expiration Date* will be automatically set to 11:59 PM on the *End Date*.

You can also specify a custom *Start Date* field to use instead of the default *Entry Date* field (so you can name it something more specific, like *Event Date*). Once you specify this field, you can automatically set the *Entry Date* to match the custom *Start Date* field, so they are always in sync.

This way, you and your clients can use friendlier custom date fields for both start and end dates, while always keeping the entry's *Entry Date* and *Expiration Date* set properly for use in `exp:channel:entries` tag parameters.

EEvent Helper also includes it's own Date fieldtype, which uses the standard pop-out calendar, but excludes the time portion of the fields and the date localization menu. The fieldtype can be used as a drop-in replacement for EE Date fields, and used without the EEvent Helper extension as well.

EEvent helper will also remove the localization menu from your chosen EE Date fields and automatically set their value to "Fixed".

You can select the following fieldtypes as Start Date and End Date fields:

* Date (default EE fieldtype)
* EEvent Helper Date (included in this download)
* [DropDate](http://devot-ee.com/add-ons/dropdate/)

*EEvent Helper has been tested on ExpressionEngine 2.1.3, and is compatible with SafeCracker.*.