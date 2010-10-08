EEvent Helper is an ExpressionEngine extension that makes managing events channels much more intuitive.

Once activiated, when entries are published in any of your specified "events" channels, their *Expiration Date* will be automatically set to 23:59:59 on the day of the event. Or, if you choose a custom date field to serve as an *End Date* indicator, their *Expiration Date* will be automatically set to 23:59:59 on the *End Date*.

You can also specify a custom *Start Date* field to use instead of the default *Entry Date* field (so you can name it something more specific, like *Event Date*). Once you specify this field, you can automatically set the *Entry Date* to match the custom *Start Date* field, so the are always in sync.

This way, you and your clients can use friendlier custom date fields for both start and end dates, while always keeping the entry's *Entry Date* and *Expiration Date* set properly for use in `exp:channel:entries` tag parameters.

Note that only fields of the built-in ExpressionEngine "Date" fieldtype can be selected as *Start Date* or *End Date* fields.

*EEvent Helper has been tested on ExpressionEngine 2.1*.