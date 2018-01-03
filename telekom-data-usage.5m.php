#!/usr/bin/php
<?php
/**
 * Creates a BitBar / Argos menu to check current data volume usage, and tariff details.
 * Can be invoked with option name and new option value to edit the configuration.
 *
 * Requires curl support, tested with PHP >= 7.0.25
 *
 * @author       Tobias Bäumer <TobiasBaeumer@gmail.com>
 * @license      AGPLv3 or later
 *
 * @todo         Add option for locale and use number_format() everywhere
 * @todo (Maybe) Proper translation by po/mo file
 * @todo         Move styling tags from translation to actual output
 * @todo         Add option for main icon, maybe with an option for the option to use base64 data instead of linux icons (mac, custom gfx)
 */

// #####################################################################################################################################

// CONFIG
/**
 * @var string $lang            Used for UI localization, but also as GET param to fetch informations. 
 *                              This means it MUST be supported by pass.telekom.de
 */
$lang = "de";

/**
 * @var bool   $tariffSubmenu   If true, tariff details will be shown in a submenu instead of the main menu.
 */
$tariffSubmenu = false;
// END CONFIG

// #####################################################################################################################################

// TRANSLATIONS
$translations = [
    'de' => [
        'volumeUsed'            => '<b>Verbraucht:</b> ',
        'volumeTotalAvailable'  => '<b>Verfügbar:</b> ',
        'volumeRemaining'       => '<b>Verbleibend:</b> ',
        'remainingTime'         => '<b>Restlaufzeit:</b> ',
        'bandwidth'             => '<b>Bandbreite:</b> ',
        'validZones'            => '<b>Gültig in:</b> ',
        'nextPass'              => '<b>Nächster Pass:</b> ',
        'remainingTimeNextPass' => '<b>Restlaufzeit n. Pass:</b> ',
        'tariffInfosLabel'      => 'Tarif Details',
        'volume'                => 'Datenvolumen',
        'activePassLabel'       => '<b>Aktiver Pass:</b> ',
        'errorGetData'          => 'Konnte Daten nicht abrufen, oder auswerten!',
        'errorRateLimiting'     => 'Anfragelimit erreicht, bitte warten!',
        'nonLinuxButtonText'    => 'Telekom.de Datenverbrauch',
        'refresh'               => 'JETZT aktualisieren',
        'actions'               => 'Aktionen',
        'open'                  => 'Öffne',
        'settings'              => 'Einstellungen',
        'options'               => [
            'tariffSubmenu' => [
                0 => 'Zeige Tarifdetails nicht in Submenü',
                1 => 'Zeige Tarifdetails in Submenü',
            ],
            'lang'          => [
                'title' => 'Sprache wechseln',
                'de'    => 'Deutsch',
                'en'    => 'English',
            ],
        ],
        'ssdDetectText'         => 'Inklusivvolumen mit hoher Geschwindigkeit verbraucht',
        'ssdTriggered'          => 'Bereits gedrosselt!',
        'errorServerInternal'   => 'Serverseitiges Problem!',
        'errorCongestion'       => 'Mobilfunknetz überlastet, keine Abfrage möglich!',
        'lastUpdatedLabel'      => '<b>Stand:</b> ',
        'lastUpdatedRxText'     => 'Letzte Aktualisierung',
        'notMeteredCurrently'   => 'Keine Erfassung derzeit',
        'unlimited'             => 'Unbegrenzt',
        'homeCountryOnly'       => 'Nur Inland',
        'orderPass'             => 'Neuen Pass buchen',
        'orderThisPass'         => 'Diesen Pass buchen',
    ],
    'en' => [
        'volumeUsed'            => '<b>Used:</b> ',
        'volumeTotalAvailable'  => '<b>Available:</b> ',
        'volumeRemaining'       => '<b>Remaining:</b> ',
        'remainingTime'         => '<b>Runtime:</b> ',
        'bandwidth'             => '<b>Bandwith:</b> ',
        'validZones'            => '<b>Valid in:</b> ',
        'nextPass'              => '<b>Next Pass:</b> ',
        'remainingTimeNextPass' => '<b>Runtime n. Pass:</b> ',
        'tariffInfosLabel'      => 'Tariff details',
        'volume'                => 'Data volume',
        'activePassLabel'       => '<b>Active Pass:</b> ',
        'errorGetData'          => 'Couldn\'t request, or parse, data!',
        'errorRateLimiting'     => 'Request limit reached, please wait!',
        'nonLinuxButtonText'    => 'Telekom.de Data usage',
        'refresh'               => 'Refresh NOW',
        'actions'               => 'Actions',
        'open'                  => 'Open',
        'settings'              => 'Settings',
        'options'               => [
            'tariffSubmenu' => [
                0 => 'Don\'t show details in submenu',
                1 => 'Show details in submenu',
            ],
            'lang'          => [
                'title' => 'Change language',
                'de'    => 'Deutsch',
                'en'    => 'English',
            ],
        ],
        'ssdDetectText'         => 'Inklusivvolumen mit hoher Geschwindigkeit verbraucht', //@todo: translate
        'ssdTriggered'          => 'Bandwidth reduced already!',
        'errorServerInternal'   => 'Serverside problem!',
        'errorCongestion'       => 'Mobile network overloaded, can\'t fetch data!',
        'lastUpdatedLabel'      => '<b>Updated:</b> ',
        'lastUpdatedRxText'     => 'Last update',
        'notMeteredCurrently'   => 'Not metered currently',
        'unlimited'             => 'Unlimited',
        'homeCountryOnly'       => 'Home country only',
        'orderPass'             => 'Order new pass',
        'orderThisPass'         => 'Book this pass'
    ],
];
// END TRANSLATIONS

// #####################################################################################################################################

// FUNCTIONS 

/**
 * Gets information about data volume
 *
 * @return array
 */
function getVolumeInformations()
{
    global $dataSource, $translations, $lang;

    $pattern = '@<span class="colored">([0-9,\.]{1,6})(?:.{1})([a-zA-Z]{2})</span>(?:[a-zA-Z\s]+)(?:[^0-9]{1})([0-9]{1,9})(?:.{1})([a-zA-Z]{2})@u';

    $matches = [];
    if (!preg_match($pattern, $dataSource, $matches)) {
        if (!strpos($dataSource, $translations[$lang]['ssdDetectText'])) {
            // No progressbar found, but no SSD warning either. Assume Unlimited
            return [
                "used"               => -2,
                "usedUnit"           => '',
                "totalAvailable"     => -2,
                "totalAvailableUnit" => '',
                "remaining"          => 0,
            ];
        }

        return [
            "used"               => -1,
            "usedUnit"           => '',
            "totalAvailable"     => -1,
            "totalAvailableUnit" => '',
            "remaining"          => 0,
        ];
    }

    $data = [
        "used"               => $matches[1],
        "usedUnit"           => $matches[2],
        "totalAvailable"     => $matches[3],
        "totalAvailableUnit" => $matches[4],
        "remaining"          => (_f($matches[3]) - _f($matches[1])),
    ];

    if ($data['usedUnit'] !== $data['totalAvailableUnit']) {
        if ($data['usedUnit'] === "MB" && $data['totalAvailableUnit'] === "GB") {
            $data['totalAvailable'] *= 1024;
            $data['totalAvailableUnit'] = "MB";
            $data['remaining'] = (_f($data['totalAvailable']) - _f($data['used']));
        }
    }

    return $data;
}

/**
 * Gets currently active pass
 *
 * @return string
 */
function getActivePassName()
{
    global $dataSource;

    $pattern = '%<h2 id="pageTitle" class="title">([^<]+)</h2>%u';

    $matches = [];
    if (preg_match($pattern, $dataSource, $matches)) {
        return $matches[1];
    } else {
        return '[UNKNOWN]';
    }
}

/**
 * Gets validity of current or next pass
 *
 * @param bool $nextPass If false, will return info for current pass
 *
 * @return string
 */
function getPassValidity($nextPass = false)
{
    $data = getTableDataByClass("remainingTime");

    if ($data !== false) {
        $val = ($nextPass) ? $data[1][1] : $data[1][0];

        return strip_tags($val);
    } else {
        return '[UNKNOWN]';
    }
}

/**
 * Get last updated "timestamp"
 * 
 * @return string
 */
function getUpdatedAt()
{
    global $dataSource, $translations, $lang;

    $pattern = '@'.$translations[$lang]['lastUpdatedRxText'].': ([^\(]+) \(@u';

    $matches = [];
    if (preg_match($pattern, $dataSource, $matches)) {
        return $matches[1];
    } else {
        return '[UNKNOWN]';
    }
}

/**
 * Gets data from pass.telekom.de info table
 *
 * @param string $className Selector
 * @param string $type      Label or Value
 *
 * @return mixed Array if data is found, else false
 */
function getTableDataByClass($className, $type = "Value")
{
    global $dataSource;

    $pattern = '%class="info' . $type . ' ' . $className . '">(.+?)</td>%u';

    $matches = [];
    if (preg_match_all($pattern, $dataSource, $matches)) {
        return $matches;
    } else {
        return false;
    }
}

/**
 * Creates volume information menu entries
 *
 * @param array $volumeInfos
 *
 * @return string
 */
function buildVolumeInformations($volumeInfos)
{
    global $translations, $lang;

    if (empty($volumeInfos)) {
        return "Couldn't get volume informations!\n";
    }

    if ($volumeInfos['totalAvailable'] === -1) {
        $percUsed      = 100;
        $percRemaining = 0;
    } elseif ($volumeInfos['totalAvailable'] === -2) {
        $percUsed      = 0;
        $percRemaining = 100;
    } else {
        $percUsed      = ((100 / _f($volumeInfos['totalAvailable'])) * _f($volumeInfos['used']));
        $percRemaining = ((100 / _f($volumeInfos['totalAvailable'])) * _f($volumeInfos['remaining']));
    }

    if (_isLinux()) {
        $labelIcon = 'network-cellular-offline';
        if ($percRemaining > 80) {
            $labelIcon = 'network-cellular-signal-excellent';
        } elseif ($percRemaining <= 80 && $percRemaining > 50) {
            $labelIcon = 'network-cellular-signal-good';
        } elseif ($percRemaining <= 50 && $percRemaining > 25) {
            $labelIcon = 'network-cellular-signal-ok';
        } elseif ($percRemaining <= 25 && $percRemaining > 5) {
            $labelIcon = 'network-cellular-signal-low';
        } elseif ($percRemaining <= 5 && $percRemaining >= 1) {
            $labelIcon = 'network-cellular-signal-none';
        }
    }

    $menuItems = "";
    $menuItems .= $translations[$lang]['volume'] . (_isLinux() ? ' | iconName=' . $labelIcon . '-symbolic' : '') . "\n";
    if ($volumeInfos['totalAvailable'] > 0) {
        $menuItems .= $translations[$lang]['volumeUsed'] . $volumeInfos['used'] . $volumeInfos['usedUnit'] . ' (' . number_format($percUsed, 2) . "%)\n";
        $menuItems .= $translations[$lang]['volumeRemaining'] . (_f($volumeInfos['totalAvailable']) - _f($volumeInfos['used'])) . $volumeInfos['totalAvailableUnit'] . ' (' . number_format($percRemaining, 2) . "%)\n";
        $menuItems .= $translations[$lang]['lastUpdatedLabel'].getUpdatedAt()."\n";
    } elseif ($volumeInfos['totalAvailable'] === -2) { 
        $menuItems .= $translations[$lang]['volumeUsed'] . $translations[$lang]['notMeteredCurrently'] . "\n";
        $menuItems .= $translations[$lang]['volumeRemaining'] . $translations[$lang]['unlimited'] . "\n";
    } else {
        $menuItems .= $translations[$lang]['volumeRemaining'] . $translations[$lang]['ssdTriggered'] . "\n";
    }

    return $menuItems;
}

/**
 * Creates tariff information menu entries
 *
 * @param array $volumeInfos
 *
 * @return string
 */
function buildTariffInformations($volumeInfos)
{
    global $translations, $lang, $tariffSubmenu;
    $menuItems = "";

    if (empty($volumeInfos)) {
        return "Couldn't get volume informations!\n";
    }

    $validInCountries = strip_tags(getTableDataByClass("tariffZone")[1][0]);
    if (empty($validInCountries)) {
        $validInCountries = $translations[$lang]['homeCountryOnly'];
    }

    $menuItems .= $translations[$lang]['tariffInfosLabel'] . " | image=iVBORw0KGgoAAAANSUhEUgAAABwAAAAYCAYAAADpnJ2CAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH4QseFSA2lgmjmgAAAjFJREFUSMftlU1IVFEUx3/n+pRxCssQUWZmIUFtCiSoJmhXQiYluStoZSBY9CGvj0UxixYRPMyCgqBo0aIWtXDlLFpVG5GgosyymGxGkRJxiJrMN/e2mOc0b2YQQ6HN/Df3nf//vHM4H5cLFVTwj5Ak9hTQ7NnDQBDYCkwDo8AuoNbTWyM4r1aSUAG1AggcieBEBQY8eySCs0fBDoH3HmettEIFrAf6wjgPPC7gndUAIZw3wEGPq12NhLMCjwrbXHQSxvkAjBS0fkUJx0I4yWX4DrMKLS1BCvt4CtuksOPL8JVibpSdPu4dUZ9tJbGvSm5uE2Gcfq9qAOMFbQf2e9yNMM54QatNCrsZOGuUqaneEnre9Lrv4aKe4Khq4b6e6bx3UqczGxH5ZQHn8jr0F87OQztwAhGAOIZx/8WSZgxn0GDmMiEgn/AHLwRg/lmiN5v+vhlRWKBzpZTk+UsYNBhdtq3GLJhFHzPv+oIY3NyHqyGb+99STXUdIlSZjJtmDgBfZI17y2ppeLK2Jypghrkw4Eu47krHR6rUAcRUSU1gglOxvNa077wm3k1drK3b6IUNIG65Rej1lmaoXEUznXdkqUX6TM+SujUVvqxE4FPyktmdW5RssdNkY0zVXz9E8HCrbhg8Zor1zOBbBQYJ1phA26ZSfWhM+O0KSOngUtiPgS7gq8C2EM7kal479W3vbQUwvf3azS+cTiDSZXJ3olGjR1L1F5/+vPtyTeWdq+C/4Q8NZbOiSooxuQAAAABJRU5ErkJggg==\n";
    $menuItems .= ($tariffSubmenu ? '--' : '') . $translations[$lang]['activePassLabel'] . getActivePassName() . "\n";
    $menuItems .= ($tariffSubmenu ? '--' : '') . $translations[$lang]['bandwidth'] . getTableDataByClass("maxBandwidth")[1][0] . "\n";
    if ($volumeInfos['totalAvailable'] > 0) {
        $menuItems .= ($tariffSubmenu ? '--' : '') . $translations[$lang]['volumeTotalAvailable'] . $volumeInfos['totalAvailable'] . $volumeInfos['totalAvailableUnit'] . "\n";
    } elseif ($volumeInfos['totalAvailable'] === -2) {
        $menuItems .= ($tariffSubmenu ? '--' : '') . $translations[$lang]['volumeTotalAvailable'] . $translations[$lang]['unlimited'] . "\n";
    }
    $menuItems .= ($tariffSubmenu ? '--' : '') . $translations[$lang]['remainingTime'] . getPassValidity() . "\n";
    $menuItems .= ($tariffSubmenu ? '--' : '') . $translations[$lang]['validZones'] . $validInCountries . " | href=http://pass.telekom.de/zoneInfo?lang=" . $lang . "\n";

    $nextPass = getTableDataByClass("passName", "Label")[1][0];
    if (!empty($nextPass)) {
        $menuItems .= ($tariffSubmenu ? '--' : '') . " ---\n";
        $menuItems .= ($tariffSubmenu ? '--' : '') . $translations[$lang]['nextPass'] . $nextPass . "\n";
        $menuItems .= ($tariffSubmenu ? '--' : '') . $translations[$lang]['remainingTimeNextPass'] . getPassValidity(true) . "\n";
    }

    return $menuItems;
}

/**
 * Convert "german" floats to real floats
 *
 * @param string $f "German" float to convert
 *
 * @return float
 */
function _f($f)
{
    global $lang;

    if ($lang === 'en') {
        return floatval($f);
    }

    return floatval(str_replace(',', '.', $f));
}

/**
 * Shorthand to determine if running on Linux / Argos or macOS
 *
 * @return bool
 */
function _isLinux()
{
    return php_uname('s') === 'Linux';
}

/**
 * Get availables passes
 * 
 * @return array
 */
function getAvailablePasses()
{
    global $dataSource, $translations, $lang;

    $pattern = '@<li class="offer_box ([^"]+)"><h2 class="offer_title">([^<]+)</h2><ul class="offer_features"><li><span class="offer_feature">([^<]+)</span></li><li><span class="offer_feature">([^<]+)</span></li></ul>(?:.+?)<span class="price">(.+?)</span></div><div class="offer_select"><a href="([^;]+)@u';

    $matches = $passes = [];
    if (preg_match_all($pattern, $dataSource, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $pass) {
            if (strpos($pass[1], "up_selling")) { // no ads please
                continue;
            }

            $passes[] = [
                'name' => $pass[2],
                'content' => $pass[3],
                'bandwidth' => $pass[4],
                'price' => strip_tags(html_entity_decode($pass[5])),
                'url' => $pass[6],
            ];
        }
    }

    return $passes;
}

// #####################################################################################################################################

// ENSURE VALID LANGUAGE
if (!in_array($lang, array_keys($translations))) {
    $lang = "en";
}

// #####################################################################################################################################

// CHANGE CONFIG HANDLING
if ($argc === 3) {
    $setOptionPattern = false;
    $replacement      = '';

    switch ($argv[1]) {
        case 'lang':
            if (!in_array($argv[2], array_keys($translations))) {
                die("Invalid value for this option! Value must be a valid language index.\n");
            }

            $setOptionPattern = '|^\$lang = (?:[\'"]{1})([' . implode('', array_keys($translations)) . ']{2})(?:[\'"]{1});$|um';
            $replacement      = '$lang = "' . $argv[2] . '";';
            break;
        case 'tariffSubmenu':
            if (!in_array($argv[2], [0, 1])) {
                die("Invalid value for this option! Value must be either 0 or 1.\n");
            }

            $setOptionPattern = '|^\$tariffSubmenu = ([truefalse]{4,5});$|um';
            $replacement      = '$tariffSubmenu = ' . ($argv[2] ? 'true' : 'false') . ';';
            break;
        default:
            die("Invalid option given!\n");
    }

    if ($setOptionPattern !== false && !empty($replacement)) {
        $script       = file_get_contents($argv[0]);
        $editedScript = preg_replace($setOptionPattern, $replacement, $script);

        if ($editedScript !== null && $editedScript !== $script) {
            file_put_contents($argv[0], $editedScript);
            echo 'Option "' . $argv[1] . '" set to "' . $argv[2] . "\"\n";
            exit(0);
        } else {
            die("Couldn't edit config!\n");
        }
    }
}

// #####################################################################################################################################

// MAIN
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_URL            => 'https://pass.telekom.de/home?lang=' . $lang,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:57.0; really:BitBar_Argos_telekom-data-usage) Gecko/20100101 Firefox/57.0',
]);
$dataSource = curl_exec($curl);
$curlInfo = curl_getinfo($curl);
curl_close($curl);
//$dataSource  = file_get_contents(__DIR__ . "/pass_ssd.htm");
//$curlInfo = ['redirect_url' => null];
$volumeInfos = getVolumeInformations();
$availablePasses = getAvailablePasses();

if (in_array("debug", $argv)) {
    echo "DATASOURCE:\n".$dataSource."\n----------------\n";
    echo "CURLINFO:\n".var_export($curlInfo, true)."\n----------------\n";
}

if (_isLinux()) {
    echo "| iconName=network-cellular-signal-excellent-symbolic\n";
} else {
    echo $translations[$lang]['nonLinuxButtonText'] . "\n";
}
echo "---\n";

if (strpos($curlInfo['redirect_url'], "429") > 0) {
    echo $translations[$lang]['errorRateLimiting'] . "\n";
} elseif (strpos($curlInfo['redirect_url'], "500") > 0) {
    echo $translations[$lang]['errorServerInternal'] . "\n";
} elseif (strpos($curlInfo['redirect_url'], "congestion") > 0) {
    echo $translations[$lang]['errorCongestion'] . "\n";
} elseif (empty($volumeInfos)) {
    echo $translations[$lang]['errorGetData'] . "\n";
} else {
    echo buildVolumeInformations($volumeInfos) . "\n";
    echo "---\n";
    echo buildTariffInformations($volumeInfos) . "\n";
    if (!empty($availablePasses)) {
        echo "---\n";
        echo $translations[$lang]['orderPass'] . "\n";
        $i = 0;
        foreach ($availablePasses as $pass) {
            if ($i > 0) {
                echo "-- ---\n";
            }
            echo '--<b>' . $pass['name'] . '</b> (' . $pass['price'] . ") | href=https://pass.telekom.de" . $pass['url'] . "\n";
            echo '--• ' . $pass['content'] . "\n";
            echo '--• ' . $pass['bandwidth'] . "\n";
            $i++;
        }
    }
}

echo "---\n";
echo $translations[$lang]['settings'] . "\n";
echo '--' . $translations[$lang]['options']['tariffSubmenu'][intval(!$tariffSubmenu)] . ' | bash=' . $argv[0] . ' param1=tariffSubmenu param2=' . intval(!$tariffSubmenu) . ' terminal=false' . "\n";

echo $translations[$lang]['options']['lang']['title'] . "\n";
foreach (array_keys($translations) as $availableLang) {
    echo '--' . $translations[$lang]['options']['lang'][$availableLang] . ' | bash=' . $argv[0] . ' param1=lang param2=' . $availableLang . ' terminal=false' . (_isLinux() ? ' iconName=indicator-keyboard-' . ucfirst($availableLang) . '' : '') . "\n";
}

echo $translations[$lang]['actions'] . "\n";
echo '--' . $translations[$lang]['refresh'] . " | refresh=true" . (_isLinux() ? ' iconName=view-refresh-symbolic' : '') . "\n";
echo '--' . $translations[$lang]['open'] . " https://pass.telekom.de/ | href=https://pass.telekom.de/home?lang=" . $lang . (_isLinux() ? ' iconName=link-symbolic' : '') . "\n";
