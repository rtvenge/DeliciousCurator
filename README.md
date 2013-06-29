#Delicious Curator

## Why this exists?

I had an issue where the plugin wasn't pulling in all of my Delicious Bookmarks. Turns out the RSS API (that this plugin was using) was the culprit and not the plug-in itself. I rewrote the data-fetching part of the plug-in to use the V1 API. :/

##Warning:## This plugin currently storing the password in plain-text in the database. Any pull request on this would be appreciated!