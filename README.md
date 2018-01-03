# telekom-data-usage - A mobile data usage monitor for german mobile provider "Telekom.de"

This little script can be used, in combination with [Argos](https://github.com/p-e-w/argos), to add a monitor applet
to your GNOME Panel that will show your currently used and remaining data. It also shows some details about your
tariff and allows you to ~~pay the internet usage ransom~~order new highspeed data volume. (Note that this doesn't 
happen in the script itself, rather it opens the ordering page for the selected pass in your default browser.)

## Installation

Download or clone the repository anywhere OUTSIDE your argos scripts directory. Then create a symlink in your
argos script directory (by default: `~/.config/argos`) pointing to telekom-data-usage.5m.php

You can change the "5m" in the link name to change the refresh interval. See [Argos documentation](https://github.com/p-e-w/argos#filename-format) for details.

## Configuration

All configuration is stored in variables at the beginning of the script. You can edit it using your favorite 
text editor. But you can also change them directly from within the applet - the script uses some self-mutating 
voodoo for that.

## Note about BitBar / Mac compability

Since Argos was created to port [BitBar](https://github.com/matryer/bitbar) scripts to Linux, this script should also work with BitBar. 
But I've never tested that.

## Screenshots

![Applet showing an unmetered 'DayFlat Unlimited'](https://github.com/mcdope/argos-telekom-data-usage/raw/master/screenshots/main-dayflat.png "Applet showing an unmetered 'DayFlat Unlimited'")

![Applet showing an already slowed down connection](https://github.com/mcdope/argos-telekom-data-usage/raw/master/screenshots/main-tariff-ssd.png "Applet showing an already slowed down connection")

![Applet showing the menu to order new volume](https://github.com/mcdope/argos-telekom-data-usage/raw/master/screenshots/main-tariff-ssd-newpass.png "Applet showing the menu to order new volume")