<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/_traits.php';  // Generell funktions

/**
 * CLASS PollenCount
 */
class PollenCount extends IPSModule
{
    use DebugHelper;
    use EventHelper;
    use ProfileHelper;
    use VariableHelper;

    // JSON Data URL
    private const JSON = 'https://opendata.dwd.de/climate_environment/health/alerts/s31fg.json';
    // Almanac URL
    private const JPEG = 'https://www.wetterdienst.de/imgs/pollenflugkalendar.jpg';
    // States (Bundesländer)
    private const STATES = [
        ['caption' => 'Schleswig—Holstein und Hamburg', 'value' => 10],
        ['caption' => 'Mecklenburg—Vorpommern ', 'value' => 20],
        ['caption' => 'Niedersachsen und Bremen', 'value' => 30],
        ['caption' => 'Nordrhein—Westfalen', 'value' => 40],
        ['caption' => 'Brandenburg und Berlin ', 'value' => 50],
        ['caption' => 'Sachsen—Anhalt', 'value' => 60],
        ['caption' => 'Thüringen', 'value' => 70],
        ['caption' => 'Sachsen', 'value' => 80],
        ['caption' => 'Hessen', 'value' => 90],
        ['caption' => 'Rheinland—Pfalz und Saarland', 'value' => 100],
        ['caption' => 'Baden—Württemberg', 'value' => 110],
        ['caption' => 'Bayern', 'value' => 120],
    ];
    // Regions (Teilgebiete)
    private const REGIONS = [
        10  => [['caption' => 'Inseln und Marschen', 'value' => 11], ['caption' => 'Geest, Schleswig-Holstein und Hamburg', 'value' => 12]],
        20  => [['caption' => 'Mecklenburg-Vorpommern ', 'value' => -1]],
        30  => [['caption' => 'Westl. Niedersachsen/Bremen', 'value' => 31], ['caption' => 'Östl. Niedersachsen', 'value' => 32]],
        40  => [['caption' => 'Rhein.-Westfäl. Tiefland', 'value' => 41], ['caption' => 'Ostwestfalen', 'value' => 42], ['caption' => 'Mittelgebirge NRW', 'value' => 43]],
        50  => [['caption' => 'Brandenburg und Berlin ', 'value' => -1]],
        60  => [['caption' => 'Tiefland Sachsen-Anhalt', 'value' => 61], ['caption' => 'Harz', 'value' => 62]],
        70  => [['caption' => 'Tiefland Thüringen', 'value' => 71], ['caption' => 'Mittelgebirge Thüringen', 'value' => 72]],
        80  => [['caption' => 'Tiefland Sachsen', 'value' => 81], ['caption' => 'Mittelgebirge Sachsen', 'value' => 82]],
        90  => [['caption' => 'Nordhessen und hess. Mittelgebirge', 'value' => 91], ['caption' => 'Rhein-Main', 'value' => 92]],
        100 => [['caption' => 'Saarland', 'value' => 103], ['caption' => 'Rhein, Pfalz, Nahe und Mosel', 'value' => 101], ['caption' => 'Mittelgebirgsbereich Rheinland-Pfalz', 'value' => 102]],
        110 => [['caption' => 'Oberrhein und unteres Neckartal', 'value' => 111], ['caption' => 'Hohenlohe/mittlerer Neckar/Oberschwaben', 'value' => 112], ['caption' => 'Mittelgebirge Baden-Württemberg', 'value' => 113]],
        120 => [['caption' => 'Allgäu/Oberbayern/Bay. Wald', 'value' => 121], ['caption' => 'Donauniederungen', 'value' => 122], ['caption' => 'Bayern n. der Donau, o. Bayr. Wald, o. Mainfranken', 'value' => 123], ['caption' => 'Mainfranken', 'value' => 124]],
    ];
    //Level (Scale to Level)
    private const LEVEL = [
        '-1' => 0, '0' => 1, '0-1'   => 2, '1' => 3, '1-2' => 4, '2' => 5, '2-3' => 6, '3' => 7,
    ];
    // Scale (Belastungsskala)
    private const SCALE = [
        '#0' => 'nicht bekannt', '#1' => 'keine', '#2' => 'keine bis gering', '#3' => 'gering', '#4' => 'gering bis mittel', '#5' => 'mittel', '#6' => 'mittel bis hoch', '#7' => 'hoch',
    ];

    /**
     * Create.
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        // Region
        $this->RegisterPropertyInteger('State', '10');
        $this->RegisterPropertyInteger('Region', '11');
        $this->RegisterPropertyInteger('Days', 2);
        // CSS
        $this->RegisterPropertyBoolean('thgrad', true);
        $this->RegisterPropertyString('table', '{border-collapse:collapse; font-size:14px; width:100%;}');
        $this->RegisterPropertyString('thead', '{font-weight:bold; color: rgb(255, 255, 255); background-color: rgb(160, 160, 0);}');
        $this->RegisterPropertyString('thtd', '{vertical-align:middle; text-align:center;  padding:5px;}');
        $this->RegisterPropertyString('trl', '{border:1px solid rgba(255, 255, 255, 0.2);}');
        $this->RegisterPropertyString('tre', '{background-color: rgba(0, 0, 0, 0.2);}');
        $this->RegisterPropertyString('tdf', '{border:1px solid rgba(255, 255, 255, 0.1);}');
        $this->RegisterPropertyString('tdm', '{border:1px solid rgba(255, 255, 255, 0.1);}');
        $this->RegisterPropertyString('tdl', '{border:1px solid rgba(255, 255, 255, 0.1);}');
        $this->RegisterPropertyString('day', '{}');
        $this->RegisterPropertyString('num', '{font-size:48px; color:rgb(160,160,0)}');
        $this->RegisterPropertyString('mon', '{text-transform:uppercase;}');
        // Settings
        $this->RegisterPropertyBoolean('CreateHint', true);
        $this->RegisterPropertyBoolean('CreateForecast', true);
        $this->RegisterPropertyBoolean('CreateLink', true);
        $this->RegisterPropertyBoolean('CreateSelect', false);
        $this->RegisterPropertyBoolean('DailyUpdate', true);
        // Profiles
        $states = [];
        foreach (self::STATES as $state) {
            $states[] = [$state['value'], $state['caption'], '', 0x800080];
        }
        $this->RegisterProfileInteger('POLLEN.States', 'Macro', '', '', 0, 0, 0, $states);
        foreach (self::REGIONS as $state => $regions) {
            $profil = [];
            foreach ($regions as $region) {
                $profil[] = [$region['value'], $region['caption'], '', -1];
            }
            $this->RegisterProfileInteger('POLLEN.' . $state, 'Image', '', '', 0, 0, 0, $profil);
        }
        $this->RegisterProfileInteger('POLLEN.Days', 'Calendar', '', '', 1, 3, 1);
        // Register daily update timer
        $this->RegisterTimer('UpdateTimer', 0, 'POLLEN_Update(' . $this->InstanceID . ');');
        $this->SendDebug('Create', 'Init Properties', 0);
    }

    /**
     * Configuration Form.
     *
     * @return JSON configuration string.
     */
    public function GetConfigurationForm()
    {
        // first we read the preperated form data
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        // States (region_id)
        $form['elements'][2]['items'][0]['items'][0]['options'] = self::STATES;
        // Regions (partregion_id)
        $state = $this->ReadPropertyInteger('State');
        $form['elements'][2]['items'][0]['items'][1]['options'] = self::REGIONS[$state];
        $this->SendDebug('GetConfigurationForm', 'state=' . $state, 0);
        return json_encode($form);
    }

    /**
     * Apply Configuration Changes.
     */
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        // Selections
        $state = $this->ReadPropertyInteger('State');
        $region = $this->ReadPropertyInteger('Region');
        $days = $this->ReadPropertyInteger('Days');
        // Creations
        $hint = $this->ReadPropertyBoolean('CreateHint');
        $forecast = $this->ReadPropertyBoolean('CreateForecast');
        $link = $this->ReadPropertyBoolean('CreateLink');
        $select = $this->ReadPropertyBoolean('CreateSelect');
        // Variables
        $this->RegisterVariableInteger('LastUpdate', $this->Translate('Last update'), '~UnixTimestamp', 0);
        $this->RegisterVariableInteger('NextUpdate', $this->Translate('Next update'), '~UnixTimestamp', 1);
        $this->MaintainVariable('Hint', $this->Translate('Tageshinweis'), VARIABLETYPE_STRING, '~TextBox', 2, $hint);
        $this->MaintainVariable('Forecast', $this->Translate('Forecast'), VARIABLETYPE_STRING, '~HTMLBox', 2, $forecast);
        $this->MaintainVariable('Link', $this->Translate('Annual calendar'), VARIABLETYPE_STRING, '~HTMLBox', 2, $link);
        $this->MaintainVariable('State', $this->Translate('State'), VARIABLETYPE_INTEGER, 'POLLEN.States', 3, $select);
        $this->MaintainVariable('Region', $this->Translate('Region'), VARIABLETYPE_INTEGER, 'POLLEN.10', 3, $select);
        $this->MaintainVariable('Days', $this->Translate('Days'), VARIABLETYPE_INTEGER, 'POLLEN.Days', 3, $select);
        if ($select) {
            if ($this->GetValue('State') == 0) {
                $state = self::STATES[0]['value'];
                $this->SetValueInteger('State', $state);
                $this->SetValueInteger('Region', self::REGIONS[$state][0]['value']);
            }
            if ($this->GetValue('Days') == 0) {
                $this->SetValueInteger('Days', 2);
            }
            $this->EnableAction('State');
            $this->EnableAction('Region');
            $this->EnableAction('Days');
        }
        // Static content (link to image)
        if ($link == true) {
            $this->SetValueString('Link', '<img src="' . self::JPEG . '" style="max-height: 100%; max-width: 100%;" \>');
        }
        // Daily Update Calculate next update interval
        $update = $this->ReadPropertyBoolean('DailyUpdate');
        if ($update == true) {
            // DWD renew data always 11 o'clock
            $this->UpdateTimerInterval('UpdateTimer', 11, 15, 0);
        } else {
            $this->SetTimerInterval('UpdateTimer', 0);
        }
        // Debug
        $this->SendDebug('ApplyChanges', 'state=' . $state . ' , region=' . $region . ' , days=' . $days . ', creates=' . ($hint ? 'Y' : 'N') . '|' . ($forecast ? 'Y' : 'N') . '|' . ($link ? 'Y' : 'N') . ', update=' . ($update ? 'Y' : 'N'), 0);
    }

    /**
     * RequestAction.
     *
     *  @param string $ident Ident.
     *  @param string $value Value.
     */
    public function RequestAction($ident, $value)
    {
        // Debug output
        $this->SendDebug('RequestAction', $ident . ' => ' . $value);
        // Ident == OnXxxxxYyyyy
        switch ($ident) {
            case 'OnSelectState':
                $this->OnSelectState($value);
                break;
            case 'State':
                $vpn = 'POLLEN.' . $value;
                $this->RegisterVariableInteger('Region', $this->Translate('Region'), $vpn, 4);
                // select the always the first
                $this->SetValueInteger('Region', self::REGIONS[$value][0]['value']);
                // No break, because 'State' have also to set the value
                // No break. Add additional comment above this line if intentional!
            case 'Region':
                // No break, because 'Region' have also to set the value
            case 'Days':
                $this->SetValueInteger($ident, $value);
                $this->Update();
                break;
        }
        // return true;
    }

    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:.
     *
     * POLLEN_Update($id);
     */
    public function Update()
    {
        // Get index info
        $json = $this->IndexInfo();
        $data = json_decode($json, true);
        // Last Update
        if (array_key_exists('last', $data)) {
            $this->SetValueInteger('LastUpdate', $data['last']);
        }
        // Next Update
        if (array_key_exists('next', $data)) {
            $this->SetValueInteger('NextUpdate', $data['next']);
        }
        // Forcast table?
        $forecast = $this->ReadPropertyBoolean('CreateForecast');
        if (($forecast == true) && array_key_exists('index', $data)) {
            $this->BuildHtml($data['index'], $data['last']);
        }
        // Daily hint ?
        $hint = $this->ReadPropertyBoolean('CreateHint');
        if (($hint == true) && array_key_exists('index', $data)) {
            $this->BuildText($data['index']);
        }
        // calculate next update interval
        $update = $this->ReadPropertyBoolean('DailyUpdate');
        if ($update == true) {
            $this->UpdateTimerInterval('UpdateTimer', 11, 15, 0);
        }
    }

    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:.
     *
     * POLLEN_IndexInfo($id);
     */
    public function IndexInfo()
    {
        // Output array
        $index = [];
        // Data source
        $json = file_get_contents(self::JSON);
        // Safty check
        if (empty($json) || $json == '') {
            $this->LogMessage($this->Translate('Error while reading the DWD pollen danger index!'), KL_ERROR);
        } else {
            $data = json_decode($json, true);
            // Last Update
            if (array_key_exists('last_update', $data)) {
                $update = str_replace(' Uhr', '', $data['last_update']);
                $last = strtotime($update);
                $index['last'] = $last;
            } else {
                $this->LogMessage($this->Translate('Error reading the last update!'), KL_WARNING);
            }
            // Next Update
            if (array_key_exists('next_update', $data)) {
                $update = str_replace(' Uhr', '', $data['next_update']);
                $next = strtotime($update);
                $index['next'] = $next;
            } else {
                $this->LogMessage($this->Translate('Error reading the next update!'), KL_WARNING);
            }
            // Legende
            $index['legend'] = self::SCALE;
            // Collect index data
            $state = $this->ReadPropertyInteger('State');
            $region = $this->ReadPropertyInteger('Region');
            if ($this->ReadPropertyBoolean('CreateSelect')) {
                $state = $this->GetValue('State');
                $region = $this->GetValue('Region');
            }
            // search
            foreach ($data['content'] as $content) {
                if (($content['region_id'] == $state) && ($content['partregion_id'] == $region)) {
                    $pollen = $content['Pollen'];
                    // Neues Array mit meinen Pollenflugdaten aufbauen
                    $pollination = [];
                    foreach ($pollen as $key => $value) {
                        $pollination[$key] = [
                            self::LEVEL[$value['today']],
                            self::LEVEL[$value['tomorrow']],
                            self::LEVEL[$value['dayafter_to']],
                        ];
                    }
                    // sort by key
                    ksort($pollination);
                    // save index data
                    $index['index'] = $pollination;
                    break;
                }
            }
        }
        // dump result
        $this->SendDebug('DATA: ', $index, 0);
        // return date info as json
        return json_encode($index);
    }

    /**
     * Execute when a user changes the selected option of the state dropdown.
     *
     * @param int $state The new selected state value.
     */
    protected function OnSelectState(int $state)
    {
        $value = json_encode(self::REGIONS[$state]);
        $this->UpdateFormField('Region', 'value', self::REGIONS[$state][0]['value']);
        $this->UpdateFormField('Region', 'options', $value);
        $this->SendDebug('OnSelectState', 'state=' . $state, 0);
    }

    /**
     * Builds the HTML Table for the forecase.
     *
     * @param array $pollination Aarray of pollen count dates.
     * @param int   $time        Number of forecast days.
     */
    private function BuildHtml(array $pollination, int $time)
    {
        // Wochentage auf Deutsch
        $day = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
        // Übersetzungstabelle
        $trans = ['January'=>'Januar', 'February'=>'Februar', 'March'=>'März', 'May'=>'Mai', 'June'=>'Juni', 'July'=>'Juli', 'October'=>'Oktober', 'December'=>'Dezember'];
        // Styles
        $style = '';
        $style = $style . '<style type="text/css">';
        // ICONS
        $style = $style . 'div.erle {margin:8px;display: inline-block;width: 32px;height: 32px;background-image: url(data:image/svg+xml;utf8;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/Pgo8IS0tIEdlbmVyYXRvcjogQWRvYmUgSWxsdXN0cmF0b3IgMTkuMC4wLCBTVkcgRXhwb3J0IFBsdWctSW4gLiBTVkcgVmVyc2lvbjogNi4wMCBCdWlsZCAwKSAgLS0+CjxzdmcgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgdmVyc2lvbj0iMS4xIiBpZD0iQ2FwYV8xIiB4PSIwcHgiIHk9IjBweCIgdmlld0JveD0iMCAwIDUxMiA1MTIiIHN0eWxlPSJlbmFibGUtYmFja2dyb3VuZDpuZXcgMCAwIDUxMiA1MTI7IiB4bWw6c3BhY2U9InByZXNlcnZlIiB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4Ij4KPGc+Cgk8Zz4KCQk8cGF0aCBkPSJNNDM3Ljg4MSwyNzkuNDZsLTUwLjI4LTE1Ni4yYy0wLjc1Ni0yLjM1LTIuMDgxLTQuNDc3LTMuODU2LTYuMTkxTDI2Ni45MzIsNC4yMTJDMjY0LjEzNSwxLjUxLDI2MC4zOTgsMCwyNTYuNTEsMGgtMS4wMTkgICAgYy0zLjg4OSwwLTcuNjI1LDEuNTEtMTAuNDIyLDQuMjEyTDEyOC4yNTUsMTE3LjA2OWMtMS43NzUsMS43MTUtMy4xLDMuODQyLTMuODU2LDYuMTkxbC01MC4yOCwxNTYuMiAgICBjLTEuMTQxLDMuNTQzLTAuOTIzLDcuMzg0LDAuNjEsMTAuNzc2bDU1LjE2MSwxMjJjMS41MzksMy40MDIsNC4yOTEsNi4xMDgsNy43MTgsNy41OUwyNDEsNDY0LjUwM1Y0OTdjMCw4LjI4NCw2LjcxNiwxNSwxNSwxNSAgICBjOC4yODQsMCwxNS02LjcxNiwxNS0xNXYtMzIuNDk3bDEwMy4zOTMtNDQuNjc3YzMuNDI3LTEuNDgxLDYuMTc5LTQuMTg4LDcuNzE4LTcuNTlsNTUuMTYxLTEyMiAgICBDNDM4LjgwNCwyODYuODQ1LDQzOS4wMjIsMjgzLjAwNCw0MzcuODgxLDI3OS40NnogTTM1Ny4xNjUsMzk0LjU4OUwyNzEsNDMxLjgyMnYtNDYuNzQ1bDg4LjY1Ny02OC41NjggICAgYzYuNTUzLTUuMDY4LDcuNzU3LTE0LjQ4OSwyLjY4OC0yMS4wNDJjLTUuMDY4LTYuNTUzLTE0LjQ4OS03Ljc1OC0yMS4wNDItMi42ODhMMjcxLDM0Ny4xNTF2LTQ4LjE3MyAgICBjMC4zOTctMC4yNTIsMC43ODktMC41MTksMS4xNjgtMC44MTNsNzcuNTc0LTU5Ljk5N2M2LjU1My01LjA2OCw3Ljc1Ny0xNC40ODksMi42ODgtMjEuMDQyICAgIGMtNS4wNjktNi41NTMtMTQuNDktNy43NTgtMjEuMDQyLTIuNjg4TDI3MSwyNjEuMTQ0di01Mi40NzFsNDguNC0zNy40MzNjNi41NTMtNS4wNjgsNy43NTctMTQuNDg5LDIuNjg4LTIxLjA0MiAgICBjLTUuMDY5LTYuNTUzLTE0LjQ5LTcuNzU4LTIxLjA0Mi0yLjY4OEwyNzEsMTcwLjc0OFY5Mi42NWMwLTguMjg0LTYuNzE2LTE1LTE1LTE1Yy04LjI4NCwwLTE1LDYuNzE2LTE1LDE1djc4LjA5N2wtMzAuMDQ3LTIzLjIzOCAgICBjLTYuNTUzLTUuMDY4LTE1Ljk3NC0zLjg2NS0yMS4wNDIsMi42ODhjLTUuMDY4LDYuNTUzLTMuODY1LDE1Ljk3NCwyLjY4OCwyMS4wNDJsNDguNCwzNy40MzN2NTIuNDcxbC02MC4zODktNDYuNzA1ICAgIGMtNi41NTQtNS4wNjctMTUuOTc1LTMuODY1LTIxLjA0MiwyLjY4OGMtNS4wNjgsNi41NTMtMy44NjUsMTUuOTc0LDIuNjg4LDIxLjA0Mmw3Ny41NzQsNTkuOTk3ICAgIGMwLjM4LDAuMjk0LDAuNzcxLDAuNTYxLDEuMTY4LDAuODEzdjQ4LjE3M2wtNzAuMzA0LTU0LjM3M2MtNi41NTMtNS4wNjgtMTUuOTc0LTMuODY0LTIxLjA0MiwyLjY4OCAgICBjLTUuMDY4LDYuNTUzLTMuODY1LDE1Ljk3NCwyLjY4OCwyMS4wNDJMMjQxLDM4NS4wNzd2NDYuNzQ1bC04Ni4xNjUtMzcuMjMzbC01MC4zODctMTExLjQ0Mmw0Ny4zNTUtMTQ3LjExNUwyNTYsMzUuMzY1ICAgIGwxMDQuMTk2LDEwMC42NjdsNDcuMzU2LDE0Ny4xMTVMMzU3LjE2NSwzOTQuNTg5eiIgZmlsbD0iI0ZGRkZGRiIvPgoJPC9nPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+Cjwvc3ZnPgo=)}';
        $style = $style . 'div.hasel {margin:8px;display: inline-block;width: 32px;height: 32px;background-image: url(data:image/svg+xml;utf8;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/Pgo8IS0tIEdlbmVyYXRvcjogQWRvYmUgSWxsdXN0cmF0b3IgMTkuMC4wLCBTVkcgRXhwb3J0IFBsdWctSW4gLiBTVkcgVmVyc2lvbjogNi4wMCBCdWlsZCAwKSAgLS0+CjxzdmcgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgdmVyc2lvbj0iMS4xIiBpZD0iQ2FwYV8xIiB4PSIwcHgiIHk9IjBweCIgdmlld0JveD0iMCAwIDQ5Ni41NDQgNDk2LjU0NCIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAwIDAgNDk2LjU0NCA0OTYuNTQ0OyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSIgd2lkdGg9IjMycHgiIGhlaWdodD0iMzJweCI+CjxnPgoJPGc+CgkJPGc+CgkJCTxwYXRoIGQ9Ik00MDguMjcyLDQ4aC0zOC41Nmw4LTQ4aC02Ni44ODhsOCw0OGgtMzguNTUyYy00OC41MiwwLTg4LDM5LjQ4LTg4LDg4djhoLTE0LjU2bDgtNDhoLTY2Ljg4OGw4LDQ4SDg4LjI3MiAgICAgYy00OC41MiwwLTg4LDM5LjQ4LTg4LDg4djMyYzAsMTAuNDE2LDYuNzA0LDE5LjIxNiwxNiwyMi41Mjh2NzUuODU2YzAsNDkuNzQ0LDMxLjI4OCw5NC44ODgsNzcuODU2LDExMi4zNmw1OC4xNDQsMjEuOCAgICAgbDU4LjE0NC0yMS44MDhjNDEuMjg4LTE1LjQ4OCw3MC41MTItNTIuNzQ0LDc2LjYyNC05NS42NjRsNTcuMjMyLDIxLjQ3Mmw1OC4xNDQtMjEuODA4ICAgICBjNDYuNTY4LTE3LjQ2NCw3Ny44NTYtNjIuNjE2LDc3Ljg1Ni0xMTIuMzZWMTkwLjUyYzkuMjg4LTMuMzEyLDE2LTEyLjExMiwxNi0yMi41Mjh2LTMyQzQ5Ni4yNzIsODcuNDcyLDQ1Ni43OTIsNDgsNDA4LjI3Miw0OHogICAgICBNMzU4LjgyNCwxNmwtNS4zMjgsMzJoLTE4LjQ0OGwtNS4zMjgtMzJIMzU4LjgyNHogTTE2Ni44MjQsMTEybC01LjMyOCwzMmgtMTguNDQ4bC01LjMyOC0zMkgxNjYuODI0eiBNMjcyLjI3MiwzNjIuMzc2ICAgICBjMCw0My4xMTItMjcuMTIsODIuMjQtNjcuNDg4LDk3LjM3NmwtNTIuNTEyLDE5LjY5NmwtNTIuNTItMTkuNjk2Yy00MC4zNi0xNS4xMjgtNjcuNDgtNTQuMjY0LTY3LjQ4LTk3LjM3NnYtNzQuMzg0aDI0MFYzNjIuMzc2ICAgICB6IE0yODAuMjcyLDI3MmgtMjU2Yy00LjQxNiwwLTgtMy41ODQtOC04di0zMmMwLTM5LjcwNCwzMi4yOTYtNzIsNzItNzJoMTI4YzM5LjcwNCwwLDcyLDMyLjI5Niw3Miw3MnYzMiAgICAgQzI4OC4yNzIsMjY4LjQwOCwyODQuNjg4LDI3MiwyODAuMjcyLDI3MnogTTQ4MC4yNzIsMTY4YzAsNC40MTYtMy41ODQsOC04LDhoLTI0djE2aDE2djc0LjM4NCAgICAgYzAsNDMuMTEyLTI3LjEyLDgyLjI0LTY3LjQ4OCw5Ny4zNzZsLTUyLjUxMiwxOS42ODhsLTUyLjUyLTE5LjY5NmMtMS4xODQtMC40NDgtMi4zMTItMC45ODQtMy40OC0xLjQ3MnYtNzUuNzYgICAgIGM5LjI4OC0zLjMxMiwxNi0xMi4xMTIsMTYtMjIuNTI4di0zMmMwLTE0LjQwOC0zLjU1Mi0yNy45ODQtOS43MTItNDBoMTM3LjcxMnYtMTZIMjg0LjEwNGMtNS4wNC02LjA4OC0xMC45MTItMTEuNDQ4LTE3LjM5Mi0xNiAgICAgaDUuNTZ2LTE2aC0xNnY5LjcxMmMtMTIuMDE2LTYuMTY4LTI1LjU5Mi05LjcxMi00MC05LjcxMmgtOHYtOGMwLTM5LjcwNCwzMi4yOTYtNzIsNzItNzJoMTI4YzM5LjcwNCwwLDcyLDMyLjI5Niw3Miw3MlYxNjh6IiBmaWxsPSIjRkZGRkZGIi8+CgkJCTxyZWN0IHg9IjI1Ni4yNzIiIHk9IjgwIiB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIGZpbGw9IiNGRkZGRkYiLz4KCQkJPHJlY3QgeD0iMjg4LjI3MiIgeT0iODAiIHdpZHRoPSIxNiIgaGVpZ2h0PSIxNiIgZmlsbD0iI0ZGRkZGRiIvPgoJCQk8cmVjdCB4PSIzMjAuMjcyIiB5PSI4MCIgd2lkdGg9IjE2IiBoZWlnaHQ9IjE2IiBmaWxsPSIjRkZGRkZGIi8+CgkJCTxyZWN0IHg9IjM1Mi4yNzIiIHk9IjgwIiB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIGZpbGw9IiNGRkZGRkYiLz4KCQkJPHJlY3QgeD0iMzg0LjI3MiIgeT0iODAiIHdpZHRoPSIxNiIgaGVpZ2h0PSIxNiIgZmlsbD0iI0ZGRkZGRiIvPgoJCQk8cmVjdCB4PSI0MTYuMjcyIiB5PSI4MCIgd2lkdGg9IjE2IiBoZWlnaHQ9IjE2IiBmaWxsPSIjRkZGRkZGIi8+CgkJCTxyZWN0IHg9IjI4OC4yNzIiIHk9IjE0NCIgd2lkdGg9IjE2IiBoZWlnaHQ9IjE2IiBmaWxsPSIjRkZGRkZGIi8+CgkJCTxyZWN0IHg9IjMyMC4yNzIiIHk9IjE0NCIgd2lkdGg9IjE2IiBoZWlnaHQ9IjE2IiBmaWxsPSIjRkZGRkZGIi8+CgkJCTxyZWN0IHg9IjM1Mi4yNzIiIHk9IjE0NCIgd2lkdGg9IjE2IiBoZWlnaHQ9IjE2IiBmaWxsPSIjRkZGRkZGIi8+CgkJCTxyZWN0IHg9IjM4NC4yNzIiIHk9IjE0NCIgd2lkdGg9IjE2IiBoZWlnaHQ9IjE2IiBmaWxsPSIjRkZGRkZGIi8+CgkJCTxyZWN0IHg9IjQxNi4yNzIiIHk9IjE0NCIgd2lkdGg9IjE2IiBoZWlnaHQ9IjE2IiBmaWxsPSIjRkZGRkZGIi8+CgkJCTxyZWN0IHg9IjQ0OC4yNzIiIHk9IjE0NCIgd2lkdGg9IjE2IiBoZWlnaHQ9IjE2IiBmaWxsPSIjRkZGRkZGIi8+CgkJCTxyZWN0IHg9IjIyNC4yNzIiIHk9IjExMiIgd2lkdGg9IjE2IiBoZWlnaHQ9IjE2IiBmaWxsPSIjRkZGRkZGIi8+CgkJCTxyZWN0IHg9IjI1Ni4yNzIiIHk9IjExMiIgd2lkdGg9IjE2IiBoZWlnaHQ9IjE2IiBmaWxsPSIjRkZGRkZGIi8+CgkJCTxyZWN0IHg9IjI4OC4yNzIiIHk9IjExMiIgd2lkdGg9IjE2IiBoZWlnaHQ9IjE2IiBmaWxsPSIjRkZGRkZGIi8+CgkJCTxyZWN0IHg9IjMyMC4yNzIiIHk9IjExMiIgd2lkdGg9IjE2IiBoZWlnaHQ9IjE2IiBmaWxsPSIjRkZGRkZGIi8+CgkJCTxyZWN0IHg9IjM1Mi4yNzIiIHk9IjExMiIgd2lkdGg9IjE2IiBoZWlnaHQ9IjE2IiBmaWxsPSIjRkZGRkZGIi8+CgkJCTxyZWN0IHg9IjM4NC4yNzIiIHk9IjExMiIgd2lkdGg9IjE2IiBoZWlnaHQ9IjE2IiBmaWxsPSIjRkZGRkZGIi8+CgkJCTxyZWN0IHg9IjQxNi4yNzIiIHk9IjExMiIgd2lkdGg9IjE2IiBoZWlnaHQ9IjE2IiBmaWxsPSIjRkZGRkZGIi8+CgkJCTxyZWN0IHg9IjQ0OC4yNzIiIHk9IjExMiIgd2lkdGg9IjE2IiBoZWlnaHQ9IjE2IiBmaWxsPSIjRkZGRkZGIi8+CgkJCTxwYXRoIGQ9Ik0xMDUuMzc2LDQ0NC43NzZsNC4wNzIsMS41Mmw1LjYwOC0xNC45ODRsLTQuMDY0LTEuNTJjLTI3Ljk0NC0xMC40NzItNDYuNzItMzcuNTY4LTQ2LjcyLTY3LjQxNnYtMi4zODRoLTE2djIuMzg0ICAgICBDNDguMjcyLDM5OC44NTYsNzEuMjE2LDQzMS45NzYsMTA1LjM3Niw0NDQuNzc2eiIgZmlsbD0iI0ZGRkZGRiIvPgoJCQk8cmVjdCB4PSI2NC4yNzIiIHk9IjE3NiIgd2lkdGg9IjE2IiBoZWlnaHQ9IjE2IiBmaWxsPSIjRkZGRkZGIi8+CgkJCTxyZWN0IHg9Ijk2LjI3MiIgeT0iMTc2IiB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIGZpbGw9IiNGRkZGRkYiLz4KCQkJPHJlY3QgeD0iMTI4LjI3MiIgeT0iMTc2IiB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIGZpbGw9IiNGRkZGRkYiLz4KCQkJPHJlY3QgeD0iMTYwLjI3MiIgeT0iMTc2IiB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIGZpbGw9IiNGRkZGRkYiLz4KCQkJPHJlY3QgeD0iMTkyLjI3MiIgeT0iMTc2IiB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIGZpbGw9IiNGRkZGRkYiLz4KCQkJPHJlY3QgeD0iMjI0LjI3MiIgeT0iMTc2IiB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIGZpbGw9IiNGRkZGRkYiLz4KCQkJPHJlY3QgeD0iMzIuMjcyIiB5PSIyNDAiIHdpZHRoPSIxNiIgaGVpZ2h0PSIxNiIgZmlsbD0iI0ZGRkZGRiIvPgoJCQk8cmVjdCB4PSI2NC4yNzIiIHk9IjI0MCIgd2lkdGg9IjE2IiBoZWlnaHQ9IjE2IiBmaWxsPSIjRkZGRkZGIi8+CgkJCTxyZWN0IHg9Ijk2LjI3MiIgeT0iMjQwIiB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIGZpbGw9IiNGRkZGRkYiLz4KCQkJPHJlY3QgeD0iMTI4LjI3MiIgeT0iMjQwIiB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIGZpbGw9IiNGRkZGRkYiLz4KCQkJPHJlY3QgeD0iMTYwLjI3MiIgeT0iMjQwIiB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIGZpbGw9IiNGRkZGRkYiLz4KCQkJPHJlY3QgeD0iMTkyLjI3MiIgeT0iMjQwIiB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIGZpbGw9IiNGRkZGRkYiLz4KCQkJPHJlY3QgeD0iMjI0LjI3MiIgeT0iMjQwIiB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIGZpbGw9IiNGRkZGRkYiLz4KCQkJPHJlY3QgeD0iMjU2LjI3MiIgeT0iMjQwIiB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIGZpbGw9IiNGRkZGRkYiLz4KCQkJPHJlY3QgeD0iMzIuMjcyIiB5PSIyMDgiIHdpZHRoPSIxNiIgaGVpZ2h0PSIxNiIgZmlsbD0iI0ZGRkZGRiIvPgoJCQk8cmVjdCB4PSI2NC4yNzIiIHk9IjIwOCIgd2lkdGg9IjE2IiBoZWlnaHQ9IjE2IiBmaWxsPSIjRkZGRkZGIi8+CgkJCTxyZWN0IHg9Ijk2LjI3MiIgeT0iMjA4IiB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIGZpbGw9IiNGRkZGRkYiLz4KCQkJPHJlY3QgeD0iMTI4LjI3MiIgeT0iMjA4IiB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIGZpbGw9IiNGRkZGRkYiLz4KCQkJPHJlY3QgeD0iMTYwLjI3MiIgeT0iMjA4IiB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIGZpbGw9IiNGRkZGRkYiLz4KCQkJPHJlY3QgeD0iMTkyLjI3MiIgeT0iMjA4IiB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIGZpbGw9IiNGRkZGRkYiLz4KCQkJPHJlY3QgeD0iMjI0LjI3MiIgeT0iMjA4IiB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIGZpbGw9IiNGRkZGRkYiLz4KCQkJPHJlY3QgeD0iMjU2LjI3MiIgeT0iMjA4IiB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIGZpbGw9IiNGRkZGRkYiLz4KCQkJPHJlY3QgeD0iNDguMjcyIiB5PSIzMjgiIHdpZHRoPSIxNiIgaGVpZ2h0PSIxNiIgZmlsbD0iI0ZGRkZGRiIvPgoJCTwvZz4KCTwvZz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8L3N2Zz4K)}';
        $style = $style . 'div.esche {margin:8px;display: inline-block;width: 32px;height: 32px;background-image: url(data:image/svg+xml;utf8;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/Pgo8IS0tIEdlbmVyYXRvcjogQWRvYmUgSWxsdXN0cmF0b3IgMTguMC4wLCBTVkcgRXhwb3J0IFBsdWctSW4gLiBTVkcgVmVyc2lvbjogNi4wMCBCdWlsZCAwKSAgLS0+CjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+CjxzdmcgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgdmVyc2lvbj0iMS4xIiBpZD0iQ2FwYV8xIiB4PSIwcHgiIHk9IjBweCIgdmlld0JveD0iMCAwIDMzOSAzMzkiIHN0eWxlPSJlbmFibGUtYmFja2dyb3VuZDpuZXcgMCAwIDMzOSAzMzk7IiB4bWw6c3BhY2U9InByZXNlcnZlIiB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4Ij4KPGc+Cgk8cGF0aCBkPSJNMTc2LjA5MywyMzEuNjNjMTcuNDEyLDAsMzQuMzIxLTYuNDk0LDQ3LjI3LTE4LjM0YzE0LjcyOS0xMy40NzcsMjMuMDEzLTMyLjY3MiwyMi43MjktNTIuNjY1ICAgYy0wLjA0My0zLjA0Mi0yLjM1Ni01LjU3LTUuMzgyLTUuODgzYy0xOS44OS0yLjA1OC0zOS43NDQsNC40OTMtNTQuNDczLDE3Ljk2OWMtNC4xLDMuNzUxLTcuNjgsNy45NTktMTAuNzM1LDEyLjQ5di0zNy4zNSAgIGMwLjE5OCwwLjAwMiwwLjM5NiwwLjAxNSwwLjU5NCwwLjAxNWMxNy40MSwwLDM0LjMyMS02LjQ5NSw0Ny4yNjktMTguMzRjMTQuNzI5LTEzLjQ3NiwyMy4wMTMtMzIuNjcxLDIyLjcyOS01Mi42NjUgICBjLTAuMDQzLTMuMDQyLTIuMzU2LTUuNTctNS4zODItNS44ODNjLTE5Ljg5LTIuMDU1LTM5Ljc0NCw0LjQ5My01NC40NzMsMTcuOTY5Yy00LjEsMy43NTEtNy42OCw3Ljk1OC0xMC43MzUsMTIuNDlWOTAuNjkyICAgYzE0LjU3NS05Ljc5MSwyMy40OTMtMjYuMzQ0LDIzLjQ5My00NC4wMDhjMC0xOC44NDMtMTAuMTQ5LTM2LjQyMy0yNi40ODgtNDUuODc3Yy0xLjg1OS0xLjA3Ni00LjE1MS0xLjA3Ni02LjAxMSwwICAgYy0xNi4zMzgsOS40NTUtMjYuNDg4LDI3LjAzNC0yNi40ODgsNDUuODc3YzAsMTcuNjY0LDguOTE4LDM0LjIxNywyMy40OTMsNDQuMDA4djEwLjc0NGMtMy4wNTUtNC41MzEtNi42MzUtOC43MzgtMTAuNzM1LTEyLjQ5ICAgYy0xNC43MjktMTMuNDc2LTM0LjU4LTIwLjAyNS01NC40NzMtMTcuOTY5Yy0zLjAyNiwwLjMxMy01LjMzOSwyLjg0MS01LjM4Miw1Ljg4M2MtMC4yODQsMTkuOTkzLDgsMzkuMTg4LDIyLjcyOSw1Mi42NjUgICBjMTIuOTQ5LDExLjg0NywyOS44NTcsMTguMzQsNDcuMjY5LDE4LjM0YzAuMTk4LDAsMC4zOTYtMC4wMTQsMC41OTQtMC4wMTV2MzcuMzVjLTMuMDU1LTQuNTMxLTYuNjM1LTguNzM5LTEwLjczNS0xMi40OSAgIGMtMTQuNzI5LTEzLjQ3Ni0zNC41OC0yMC4wMjUtNTQuNDczLTE3Ljk2OWMtMy4wMjYsMC4zMTMtNS4zMzksMi44NDEtNS4zODIsNS44ODNjLTAuMjg0LDE5Ljk5Myw4LDM5LjE4OCwyMi43MjksNTIuNjY1ICAgYzEyLjk0OSwxMS44NDcsMjkuODU2LDE4LjM0LDQ3LjI3LDE4LjM0YzAuMTk3LDAsMC4zOTYtMC4wMTQsMC41OTMtMC4wMTV2MzcuMzVjLTMuMDU1LTQuNTMxLTYuNjM1LTguNzM4LTEwLjczNS0xMi40OSAgIGMtMTQuNzI5LTEzLjQ3Ni0zNC41OC0yMC4wMjUtNTQuNDczLTE3Ljk2OWMtMy4wMjYsMC4zMTMtNS4zMzksMi44NDEtNS4zODIsNS44ODNjLTAuMjg0LDE5Ljk5Myw4LDM5LjE4OCwyMi43MjksNTIuNjY1ICAgYzEyLjk0OSwxMS44NDcsMjkuODU2LDE4LjM0LDQ3LjI3LDE4LjM0YzAuMTk3LDAsMC4zOTYtMC4wMTQsMC41OTMtMC4wMTVWMzMzYzAsMy4zMTMsMi42ODYsNiw2LDZzNi0yLjY4Nyw2LTZ2LTE3LjYyMiAgIGMwLjE5NywwLjAwMiwwLjM5NiwwLjAxNSwwLjU5MywwLjAxNWMxNy40MTIsMCwzNC4zMjEtNi40OTQsNDcuMjctMTguMzRjMTQuNzI5LTEzLjQ3NiwyMy4wMTMtMzIuNjcxLDIyLjcyOS01Mi42NjUgICBjLTAuMDQzLTMuMDQyLTIuMzU2LTUuNTctNS4zODItNS44ODNjLTE5Ljg5LTIuMDU3LTM5Ljc0NCw0LjQ5My01NC40NzMsMTcuOTY5Yy00LjEsMy43NTEtNy42OCw3Ljk1OC0xMC43MzUsMTIuNDl2LTM3LjM1ICAgQzE3NS42OTcsMjMxLjYxNiwxNzUuODk2LDIzMS42MywxNzYuMDkzLDIzMS42M3ogTTE5NC4zMzYsMTgxLjU2NGMxMC44MjYtOS45MDUsMjQuOTUzLTE1LjI3NywzOS41NjctMTUuMTkyICAgYy0xLjE5MiwxNC41MjItNy44MTUsMjguMTU5LTE4LjY0MiwzOC4wNjRjLTEwLjcyNyw5LjgxNC0yNC43MzcsMTUuMTk0LTM5LjE2NywxNS4xOTRjLTAuMTMzLDAtMC4yNjcsMC0wLjQtMC4wMDEgICBDMTc2Ljg4NiwyMDUuMTA2LDE4My41MSwxOTEuNDcsMTk0LjMzNiwxODEuNTY0eiBNMTk0LjMzNiw5Ny44YzEwLjgyNi05LjkwNSwyNC45NTMtMTUuMzAxLDM5LjU2Ny0xNS4xOTIgICBjLTEuMTkyLDE0LjUyMi03LjgxNSwyOC4xNTktMTguNjQyLDM4LjA2NGMtMTAuODI4LDkuOTA2LTI1LjAzNSwxNS4zMTEtMzkuNTY3LDE1LjE5MiAgIEMxNzYuODg2LDEyMS4zNDMsMTgzLjUxLDEwNy43MDYsMTk0LjMzNiw5Ny44eiBNMTIzLjczOCwxMjAuNjcyYy0xMC44MjYtOS45MDUtMTcuNDUtMjMuNTQyLTE4LjY0Mi0zOC4wNjQgICBjMTQuNjE4LTAuMTE3LDI4Ljc0Miw1LjI4OCwzOS41NjcsMTUuMTkyYzEwLjgyNiw5LjkwNSwxNy40NSwyMy41NDIsMTguNjQyLDM4LjA2NCAgIEMxNDguNzQxLDEzNS45NzIsMTM0LjU2NCwxMzAuNTc3LDEyMy43MzgsMTIwLjY3MnogTTE2Mi45MDUsMjE5LjYzYy0xNC40MjksMC0yOC40NC01LjM4LTM5LjE2Ny0xNS4xOTQgICBjLTEwLjgyNi05LjkwNi0xNy40NS0yMy41NDItMTguNjQyLTM4LjA2NGMxNC42MTgtMC4wOTgsMjguNzQyLDUuMjg4LDM5LjU2NywxNS4xOTJjMTAuODI2LDkuOTA2LDE3LjQ1LDIzLjU0MiwxOC42NDIsMzguMDY0ICAgQzE2My4xNzIsMjE5LjYyOSwxNjMuMDM4LDIxOS42MywxNjIuOTA1LDIxOS42M3ogTTE2Mi45MDUsMzAzLjM5NGMtMTQuNDI5LDAtMjguNDQtNS4zOC0zOS4xNjctMTUuMTk0ICAgYy0xMC44MjYtOS45MDUtMTcuNDUtMjMuNTQyLTE4LjY0Mi0zOC4wNjRjMC4xMzMtMC4wMDEsMC4yNjgtMC4wMDEsMC40MDEtMC4wMDFjMTQuNDI4LDAsMjguNDQsNS4zOCwzOS4xNjcsMTUuMTk0ICAgYzEwLjgyNiw5LjkwNSwxNy40NSwyMy41NDIsMTguNjQyLDM4LjA2NEMxNjMuMTcyLDMwMy4zOTMsMTYzLjAzOCwzMDMuMzk0LDE2Mi45MDUsMzAzLjM5NHogTTE5NC4zMzYsMjY1LjMyOCAgIGMxMC44MjYtOS45MDUsMjUuMDI3LTE1LjI4NiwzOS41NjctMTUuMTkyYy0xLjE5MiwxNC41MjItNy44MTUsMjguMTU5LTE4LjY0MiwzOC4wNjRjLTEwLjcyNyw5LjgxNC0yNC43MzcsMTUuMTk0LTM5LjE2NywxNS4xOTQgICBjLTAuMTMzLDAtMC4yNjcsMC0wLjQtMC4wMDFDMTc2Ljg4NiwyODguODcsMTgzLjUxLDI3NS4yMzMsMTk0LjMzNiwyNjUuMzI4eiBNMTUyLjAwNyw0Ni42ODVjMC0xMy4zODMsNi42MjItMjUuOTQzLDE3LjQ5My0zMy41NzIgICBjMTAuODcyLDcuNjI5LDE3LjQ5MywyMC4xODksMTcuNDkzLDMzLjU3MnMtNi42MjIsMjUuOTQzLTE3LjQ5MywzMy41NzJDMTU4LjYyOCw3Mi42MjcsMTUyLjAwNyw2MC4wNjcsMTUyLjAwNyw0Ni42ODV6IiBmaWxsPSIjRkZGRkZGIi8+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPC9zdmc+Cg==)}';
        $style = $style . 'div.birke {margin:8px;display: inline-block;width: 32px;height: 32px;background-image: url(data:image/svg+xml;utf8;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/Pgo8IS0tIEdlbmVyYXRvcjogQWRvYmUgSWxsdXN0cmF0b3IgMTkuMC4wLCBTVkcgRXhwb3J0IFBsdWctSW4gLiBTVkcgVmVyc2lvbjogNi4wMCBCdWlsZCAwKSAgLS0+CjxzdmcgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgdmVyc2lvbj0iMS4xIiBpZD0iQ2FwYV8xIiB4PSIwcHgiIHk9IjBweCIgdmlld0JveD0iMCAwIDUxMi4wMDEgNTEyLjAwMSIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAwIDAgNTEyLjAwMSA1MTIuMDAxOyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSIgd2lkdGg9IjMycHgiIGhlaWdodD0iMzJweCI+CjxnPgoJPGc+CgkJPHBhdGggZD0iTTI4Ni4yODcsNDc5Ljk2M2gtOS4zNTVjLTIuNzEzLDAtNC45MTQtMi4yMDEtNC45MTQtNC45MTR2LTQzLjE0N0gyMzkuOTh2NDcuOTY2YzAsMTcuNzQ2LDE0LjM4NiwzMi4xMzMsMzIuMTMzLDMyLjEzMyAgICBoMTQuMTc0YzguODQ3LDAsMTYuMDE5LTcuMTcyLDE2LjAxOS0xNi4wMTlDMzAyLjMwNiw0ODcuMTM1LDI5NS4xMzQsNDc5Ljk2MywyODYuMjg3LDQ3OS45NjN6IiBmaWxsPSIjRkZGRkZGIi8+Cgk8L2c+CjwvZz4KPGc+Cgk8Zz4KCQk8cGF0aCBkPSJNNDQ1Ljk5OCwyNjAuMzE3aC02Ljk1MWMtMi44NDUsMC01LjY4NC0wLjY2Mi04LjEyNy0yLjEyMWMtNS4xNjUtMy4wODQtNy45MjUtOC43MjgtNy40MzQtMTQuNDQ3bDQuNjg5LTU0LjUwMyAgICBjMC40NTUtNS4yOC01LjYwNS04LjYwMy05LjgyMy01LjMwN2MtMS42NDksMS4yODktMy40NzgsMi4zNjEtNS41MzEsMi43NjdjLTkuNzI5LDEuOTI2LTE3LjY0My00LjUxNS0xOC43ODgtMTIuODczICAgIGwtOC40MTItNjEuNDEyYy0wLjQxMi0zLjAxNC0yLjk4OC01LjI2LTYuMDI5LTUuMjZoLTE4LjU3NGMtNS40ODYsMC0xMC41NS0yLjk0NS0xMy4yNjQtNy43MTRsLTI0Ljc2Ny00My41MyAgICBjLTIuNTE2LTQuNDIxLTcuOTg4LTYuMTk1LTEyLjYxOS00LjA5Yy05LjEzMSw0LjE1MS0xOS45MTQsMC40OTYtMjQuNjM2LTguMzU0QzI3OS4xMjEsMzEuMDgyLDI3Mi4xNCwxNy45NjUsMjY1LjczLDUuODYgICAgYy00LjEzOC03LjgxMy0xNS4zMjMtNy44MTMtMTkuNDYxLDBjLTYuNDExLDEyLjEwNS0xMy4zOTIsMjUuMjIyLTIwLjAwNCwzNy42MTJjLTQuNzIyLDguODQ5LTE1LjUwNSwxMi41MDUtMjQuNjM2LDguMzU0ICAgIGMtNC42MzEtMi4xMDUtMTAuMTA0LTAuMzMxLTEyLjYxOSw0LjA5bC0yNC43NjcsNDMuNTMyYy0yLjcxMyw0Ljc2OS03Ljc3Nyw3LjcxMy0xMy4yNjQsNy43MTNoLTE4LjU3MyAgICBjLTMuMDQyLDAtNS42MTcsMi4yNDYtNi4wMjksNS4yNmwtOC4zNzEsNjEuMTA3Yy0wLjU2LDQuMDg0LTIuNjA5LDcuODc2LTUuODk2LDEwLjM2M2MtNS41NjgsNC4yMTItMTMuMDE1LDQuMDkxLTE4LjM2NCwwLjExOCAgICBsLTAuMjMtMC4xNzFjLTQuMjEtMy4xMy0xMC4xNDIsMC4xNzgtOS42OTIsNS40MDRsNC42ODksNTQuNTA1YzAuNDkyLDUuNzE5LTIuMjY4LDExLjM2My03LjQzNCwxNC40NDcgICAgYy0yLjQ0MywxLjQ1OS01LjI4MSwyLjEyMS04LjEyNywyLjEyMUg2NmMtNC4wMjUsMC02LjkzMywzLjgzMS01Ljg2NSw3LjcxMWM1LjEwNywxOC41NjMsMTQuMjk2LDQ1LjA2NSwyOS45MjYsNzEuMjQ4ICAgIGMzNC45MTksNTguNDk1LDg1LjMzMSw4OS41OTUsMTQ5LjkyLDkyLjYyNnYtNTMuMjU5Yy0yNy4wNTUtMjMuMTYxLTkzLjcwMy04MC4xOTktOTYuNzg5LTgyLjc2NiAgICBjLTYuNDg5LTUuMjgzLTcuNTE2LTE0LjgyMS0yLjI3OC0yMS4zNjZjNS4yNjUtNi41OCwxNC44NjktNy42NDcsMjEuNDQ4LTIuMzhjMS40MzEsMS4xNDUsNDUuMjk5LDM4LjY3Nyw3Ny42MTksNjYuMzM5di01Mi41MzkgICAgYy0yNy44OTgtMjMuODgxLTY4LjkzMy01OC45OTMtNzEuMjM5LTYwLjkxN2MtNi40NzEtNS4yODYtNy40OTEtMTQuODEtMi4yNi0yMS4zNDljNS4yNjUtNi41ODEsMTQuODY4LTcuNjQ5LDIxLjQ0OS0yLjM4MSAgICBjMS4wMDksMC44MDcsMjcuNjI4LDIzLjU3NSw1Mi4wNSw0NC40NzZ2LTQ0LjEwNWMtMTcuMjMzLTE0Ljc1Mi00Mi4yMjctMzYuMTM4LTQzLjkxOC0zNy41NTUgICAgYy02LjY5LTUuNDk1LTcuNTItMTUuNTMzLTEuNTk3LTIyLjA2NGM1LjU2Ny02LjEzNiwxNS4xMzMtNi40NiwyMS40NTctMS4xMDZjMS40MzksMS4yMTgsNi4zNTcsNS40MSwyNC4wNTcsMjAuNTU1Vjg2Ljg2NyAgICBjMC04LjYyNSw2LjYyOS0xNi4wNDcsMTUuMjQ1LTE2LjQ1M2M5LjE5OS0wLjQzNCwxNi43OTMsNi44OTcsMTYuNzkzLDE2LjAwMXY3NS4wNzFjMTcuNjk5LTE1LjE0NCwyMi42MTgtMTkuMzM3LDI0LjA1Ny0yMC41NTUgICAgYzYuMzIzLTUuMzUzLDE1Ljg5LTUuMDMxLDIxLjQ1NywxLjEwNWM1LjkyNCw2LjUzLDUuMDk0LDE2LjU3LTEuNTk2LDIyLjA2NGMtMS42OTEsMS40MTgtMjYuNjg1LDIyLjgwMy00My45MTgsMzcuNTU1djQ0LjEwNSAgICBjMjQuNDIyLTIwLjkwMSw1MS4wNC00My42Nyw1Mi4wNDktNDQuNDc3YzYuNTgxLTUuMjY2LDE2LjE4NC00LjIsMjEuNDQ5LDIuMzgxYzUuMjMyLDYuNTM5LDQuMjEyLDE2LjA2My0yLjI2LDIxLjM0OSAgICBjLTIuMzA2LDEuOTI1LTQzLjM0MiwzNy4wMzctNzEuMjM5LDYwLjkxOHY1Mi41MzljMzIuMzE5LTI3LjY2Myw3Ni4xODgtNjUuMTk1LDc3LjYxOS02Ni4zNGM2LjU4MS01LjI2NSwxNi4xODMtNC4yLDIxLjQ0OCwyLjM4ICAgIGM1LjIzOCw2LjU0Niw0LjIxMSwxNi4wODMtMi4yNzgsMjEuMzY2Yy0zLjA4NywyLjU2Ni02OS43MzQsNTkuNjA1LTk2Ljc4OSw4Mi43NjZWNDMxLjkgICAgYzY0LjU4OS0zLjAzMSwxMTUuMDAxLTM0LjEzMSwxNDkuOTItOTIuNjI2YzE1LjYyOS0yNi4xODEsMjQuODE4LTQ3LjY3NSwyOS45MjUtNjYuMjQyICAgIEM0NTIuOTMyLDI2OS4xNTMsNDUwLjAyMywyNjAuMzE3LDQ0NS45OTgsMjYwLjMxN3oiIGZpbGw9IiNGRkZGRkYiLz4KCTwvZz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8L3N2Zz4K)}';
        $style = $style . 'div.graeser {margin:8px;display: inline-block;width: 32px;height: 32px;background-image: url(data:image/svg+xml;utf8;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/Pgo8IS0tIEdlbmVyYXRvcjogQWRvYmUgSWxsdXN0cmF0b3IgMTkuMC4wLCBTVkcgRXhwb3J0IFBsdWctSW4gLiBTVkcgVmVyc2lvbjogNi4wMCBCdWlsZCAwKSAgLS0+CjxzdmcgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgdmVyc2lvbj0iMS4xIiBpZD0iQ2FwYV8xIiB4PSIwcHgiIHk9IjBweCIgdmlld0JveD0iMCAwIDQ1MyA0NTMiIHN0eWxlPSJlbmFibGUtYmFja2dyb3VuZDpuZXcgMCAwIDQ1MyA0NTM7IiB4bWw6c3BhY2U9InByZXNlcnZlIiB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4Ij4KPGc+Cgk8Zz4KCQk8cGF0aCBkPSJNNDUxLjIsMTAwLjVjLTMuMi0zLjItNy4yLTMuMi0xMC40LTAuOGMtNCwzLjItNzcuNiw2NC44LTExNi44LDE2Ny4yYy03LjItMTguNC0xNS4yLTM2LjgtMjQtNTIuOCAgICBjMjAuOC02OCw0MS42LTExNS4yLDYzLjItMTQwYzIuNC0zLjIsMi40LTgtMC44LTExLjJjLTMuMi0zLjItOC0zLjItMTEuMiwwYy0xLjYsMS42LTQwLjgsNDAuOC04MCwxMDEuNiAgICBjLTguOC0xMy42LTE3LjYtMjUuNi0yNi40LTM2LjhjMTAuNC0yMy4yLDIxLjYtNDEuNiwzMi01NC40YzIuNC0zLjIsMi40LTgtMC44LTExLjJjLTMuMi0zLjItOC0zLjItMTEuMiwwICAgIGMtMC44LDAuOC0xNi44LDE2LjgtMzguNCw0Mi40Yy0yMC44LTI1LjYtMzYuOC00MC44LTM4LjQtNDIuNGMtMy4yLTMuMi04LTMuMi0xMS4yLDBjLTMuMiwzLjItMy4yLDgtMC44LDExLjIgICAgYzExLjIsMTMuNiwyMi40LDMyLDMyLDU0LjRjLTguOCwxMS4yLTE2LjgsMjMuMi0yNS42LDM2LjhjLTM4LjQtNjAuOC03Ny42LTEwMC04MC0xMDEuNmMtMy4yLTMuMi04LTMuMi0xMS4yLDAgICAgYy0zLjIsMy4yLTMuMiw4LTAuOCwxMS4yYzI0LjgsMjguOCw0Ni40LDg0LDYzLjIsMTQwYy05LjYsMTcuNi0xNy42LDM2LTI0LDUzLjZDODkuNiwxNjQuNSwxNiwxMDIuOSwxMi44LDk5LjcgICAgYy0zLjItMi40LTgtMi40LTEwLjQsMC44Yy0zLjIsMy4yLTMuMiw4LDAsMTEuMkM1NiwxNzQuOSw4Mi40LDMzNy4zLDg4LDM3Ny4zSDQwLjh2MGMtNC44LDAtOCwzLjItOCw4czMuMiw4LDgsOEg5NmgxNS4yaDM0LjQgICAgaDE1LjJoNDAuOGgwLjhoNDhoMC44SDI5MmgxNS4yaDM0LjRoMTUuMmg1OC40YzQuOCwwLDgtMy4yLDgtOHMtMy4yLTgtOC04aC00OS42YzUuNi00MCwzMi0yMDIuNCw4NS42LTI2NS42ICAgIEM0NTMuNiwxMDguNSw0NTMuNiwxMDMuNyw0NTEuMiwxMDAuNXogTTMwOS42LDEzNy4zYy03LjIsMTYuOC0xMy42LDM2LjgtMjAuOCw1OC40Yy0zLjItNC44LTUuNi05LjYtOC44LTE0LjQgICAgQzI4OS42LDE2NS4zLDMwMCwxNTAuMSwzMDkuNiwxMzcuM3ogTTI0MCwyMTcuM2MtNCwxMC40LTguOCwyMC44LTEzLjYsMzEuMmMtNC44LTExLjItOS42LTIxLjYtMTUuMi0zMiAgICBjNC44LTE2LDkuNi0zMC40LDE0LjQtNDMuMkMyMzEuMiwxODcuNywyMzYsMjAyLjEsMjQwLDIxNy4zeiBNMjE2LDE0Ni4xYzAuOCwxLjYsMS42LDMuMiwxLjYsNC44Yy01LjYsMTQuNC0xMS4yLDI5LjYtMTYuOCw0Ni40ICAgIGMtMy4yLTUuNi02LjQtMTEuMi05LjYtMTZDMjAwLDE2OC41LDIwOCwxNTYuNSwyMTYsMTQ2LjF6IE0xMDQsMzc3LjNoLTAuOGMtNC0yOC0yMC0xMzYtNTQuNC0yMTUuMmMyNC44LDMxLjIsNTMuNiw3NS4yLDcyLDEzMC40ICAgIEMxMTEuMiwzMjEuMywxMDYuNCwzNTAuMSwxMDQsMzc3LjN6IE0xNTMuNiwzNzcuM2gtOC44aC0yNS42YzMuMi00NC44LDE2LjgtOTIsNDAtMTQwYzUuNiwxOS4yLDEwLjQsMzcuNiwxNC40LDU0LjQgICAgQzE2My4yLDMzMS43LDE1Ni44LDM2NC41LDE1My42LDM3Ny4zeiBNMTcwLjQsMzc3LjNjMi40LTEwLjQsNS42LTI4LjgsMTEuMi01MS4yYzUuNiwyMy4yLDguOCw0MS42LDExLjIsNTEuMkgxNzAuNHogICAgIE0xOTkuMiwzMzMuM2MtMTEuMi01MS4yLTMwLjQtMTMyLTU2LjgtMTk1LjJjMjYuNCwzMy42LDU1LjIsNzkuMiw3NS4yLDEzMkMyMDkuNiwyOTEuNywyMDQsMzEyLjUsMTk5LjIsMzMzLjN6IE0yNDQsMzc2LjUgICAgaC0zNC4zMzFjMy4yOTYtNDMuNzYzLDE2LjA2Ni04OS44OSwzOC4zMzEtMTM3LjZjNS42LDE4LjQsMTAuNCwzNiwxNC40LDUyQzI1Mi44LDMzMC4xLDI0Ni40LDM2Mi45LDI0NCwzNzYuNXogTTI2MC44LDM3Ny4zICAgIGMxLjYtMTAuNCw1LjYtMjguOCwxMC40LTUxLjJjNC44LDIzLjIsOC44LDQxLjYsMTEuMiw1MS4ySDI2MC44eiBNMjk5LjIsMzc4LjFoLTAuOGMtNS42LTI4LjgtMjguOC0xNDAtNjMuMi0yMjcuMiAgICBjMC44LTEuNiwxLjYtMy4yLDEuNi00LjhjMjgsMzguNCw1OS4yLDg4LjgsNzguNCwxNDYuNEMzMDcuMiwzMTguOSwzMDEuNiwzNDYuOSwyOTkuMiwzNzguMXogTTM0OS42LDM3Ny4zaC04SDMxNiAgICBjNi40LTk0LjQsNTItMTY5LjYsODgtMjE1LjJDMzY5LjYsMjQyLjEsMzUzLjYsMzQ5LjMsMzQ5LjYsMzc3LjN6IiBmaWxsPSIjRkZGRkZGIi8+Cgk8L2c+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPC9zdmc+Cg==)}';
        $style = $style . 'div.roggen {margin:8px;display: inline-block;width: 32px;height: 32px;background-image: url(data:image/svg+xml;utf8;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/Pgo8IS0tIEdlbmVyYXRvcjogQWRvYmUgSWxsdXN0cmF0b3IgMTcuMS4wLCBTVkcgRXhwb3J0IFBsdWctSW4gLiBTVkcgVmVyc2lvbjogNi4wMCBCdWlsZCAwKSAgLS0+CjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+CjxzdmcgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgdmVyc2lvbj0iMS4xIiBpZD0iQ2FwYV8xIiB4PSIwcHgiIHk9IjBweCIgdmlld0JveD0iMCAwIDQxNi45NzMgNDE2Ljk3MyIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAwIDAgNDE2Ljk3MyA0MTYuOTczOyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSIgd2lkdGg9IjMycHgiIGhlaWdodD0iMzJweCI+CjxwYXRoIGlkPSJYTUxJRF84MDhfIiBkPSJNMzU2LjQwNyw0MTYuOTczYzQuNDE4LDAsOC0zLjU4Miw4LThzLTMuNTgyLTgtOC04aC05My45MzZsMTguMDQxLTQyLjA5NiAgYzAuODk2LDAuMDg4LDEuNzk3LDAuMTQ2LDIuNzA0LDAuMTQ2YzEuMTA2LDAsMi4yMTktMC4wNjYsMy4zMzMtMC4xOTljNS4wMzMtMC42LDkuNzA1LTIuNTI0LDEzLjYzMS01LjU1bDM4LjkwMy0yOC4yMzYgIGM1Ljk0OC00LjMxNyw5LjgzOC0xMC43NDYsMTAuOTUyLTE4LjEwM2MxLjAyLTYuNzM1LTAuNDkyLTEzLjY4NS00LjE0Ny0xOS4zNjhsMTMuNzEyLTEwLjI1OSAgYzExLjc3Ny04LjgxNCwxNC41NzgtMjUuMjI3LDYuNzEzLTM3LjQwM2wxMy43MS0xMC4yNTdjNS45NC00LjQ0NSw5Ljc5Ny0xMC45NTcsMTAuODYtMTguMzM3ICBjMC44MDctNS42MDYtMC4wNzctMTEuMTg0LTIuNDk0LTE2LjE0NmwyMy42MjgtNTUuMTM4YzEuNzQtNC4wNjEtMC4xNDEtOC43NjQtNC4yMDMtMTAuNTA0cy04Ljc2NCwwLjE0MS0xMC41MDQsNC4yMDMgIEwzNzYuMzI0LDE4Mi43Yy0yLjc3OC0xLjQ1Ni01LjgxOC0yLjQ0Ni05LjAyMi0yLjkwN2MtMy40NTYtMC40OTgtNi45MDItMC4zNDYtMTAuMjAzLDAuNDA2bDMxLjQzNS03My40MTIgIGMxLjczOS00LjA2Mi0wLjE0NC04Ljc2NC00LjIwNS0xMC41MDNjLTQuMDYtMS43MzktOC43NjMsMC4xNDQtMTAuNTAzLDQuMjA1bC0zMS40NDYsNzMuNDM3Yy0xLjczNS0yLjkyNi00LjAxMS01LjU0Mi02Ljc3Mi03LjcxNCAgYy0yLjU0My0yLjAwMS01LjM1NC0zLjUxOS04LjMyNC00LjUyNmwyMC45ODYtNDguOTc1YzEuNzQtNC4wNjEtMC4xNDItOC43NjQtNC4yMDMtMTAuNTA0Yy00LjA2Mi0xLjc0LTguNzY0LDAuMTQxLTEwLjUwNCw0LjIwMyAgbC0yMy42MjcsNTUuMTRjLTEyLjQ3MiwzLjk3MS0yMC44MDcsMTYuMzctMTkuMjAyLDI5LjgzOGwyLjAyOSwxN2MtMTQuMjQxLDIuNzA0LTI0LjE5NCwxNi4wNTMtMjIuNDUyLDMwLjY1OWwyLjAyOSwxNy4wMDIgIGMtMTQuMjQxLDIuNzA0LTI0LjE5MywxNi4wNTItMjIuNDUxLDMwLjY1OGw1LjY5OSw0Ny43NTZ2LTAuMDAxYzAuODYzLDcuMjMxLDQuNDU5LDEzLjcxNSwxMC4xMzIsMTguMzEzbC0yMC42NTYsNDguMTk5aC0yOC41NDYgIHYtMTE4LjVjNy4wMjMtMS45OTEsMTIuODgyLTYuNTM0LDE2LjUyMi0xMi44MzlsMjQuMDQ3LTQxLjY1MWMzLjcwOS02LjQyNSw0LjY4OS0xMy45MywyLjc1OS0yMS4xMzIgIGMtMS43NjEtNi41NzMtNS43NTMtMTIuMTQ2LTExLjMxOS0xNS44OTFsOC41NjMtMTQuODNjNy4zNTMtMTIuNzM5LDMuNDYzLTI4LjkyOC04LjU2MS0zNy4wMjNsOC41NjEtMTQuODI4ICBjNi43OC0xMS43NDYsNC4wMDItMjYuNDI0LTUuODk1LTM0Ljk4NlYyOS4zMDFjMC00LjQxOC0zLjU4Mi04LTgtOHMtOCwzLjU4Mi04LDh2NTMuMjgzYy0zLjEyNy0wLjI0NC02LjMwOSwwLjA0NC05LjQzNiwwLjg4MSAgYy0zLjM4MywwLjkwNi02LjQ5OSwyLjQwOS05LjI0MSw0LjQwN1Y4YzAtNC40MTgtMy41ODItOC04LThzLTgsMy41ODItOCw4djc5Ljg3NGMtNS4zODgtMy45My0xMi4wNDMtNS44MS0xOC42NzgtNS4yODdWMjkuMzAxICBjMC00LjQxOC0zLjU4Mi04LTgtOHMtOCwzLjU4Mi04LDh2NTkuOTkzYy05Ljg5Niw4LjU2NC0xMi42NzMsMjMuMjQxLTUuODkzLDM0Ljk4NWw4LjU2MSwxNC44MjcgIGMtMTIuMDI1LDguMDk1LTE1LjkxNSwyNC4yODQtOC41NjEsMzcuMDI0bDguNTYyLDE0LjgzYy01LjU5OCwzLjc4NC05LjcyNCw5LjU3Ny0xMS40NCwxNi4xNjkgIGMtMS44NzQsNy4yLTAuODMyLDE0LjY0MiwyLjkzNSwyMC45NTRsMjQuNjAzLDQxLjIxOGMyLjQxOCw0LjM1NSw1Ljk2OCw3Ljk4NCwxMC4zNzcsMTAuNTI5YzEuNzcxLDEuMDIzLDMuNjI2LDEuODI1LDUuNTMyLDIuNDMgIHYxMTguNzE0aC0yOC42MDlsLTIwLjY2LTQ4LjE5NmM1LjY3NC00LjU5Nyw5LjI3MS0xMS4wODIsMTAuMTM1LTE4LjMxNHYwbDUuNjk5LTQ3Ljc1N2MxLjc0MS0xNC42MDUtOC4yMS0yNy45NTQtMjIuNDUyLTMwLjY1OCAgbDIuMDI5LTE3LjAwMmMxLjc0Mi0xNC42MDYtOC4yMDktMjcuOTU1LTIyLjQ1MS0zMC42NTlsMi4wMjktMTcuMDAxYzEuNjA2LTEzLjQ2OC02LjczLTI1Ljg2NS0xOS4yMDEtMjkuODM3bC0yMy42MjgtNTUuMTQxICBjLTEuNzQtNC4wNjEtNi40NDItNS45NDMtMTAuNTA0LTQuMjAzYy00LjA2MSwxLjc0LTUuOTQzLDYuNDQzLTQuMjAzLDEwLjUwNGwyMC45ODYsNDguOTc3Yy0yLjk3LDEuMDA3LTUuNzgyLDIuNTI1LTguMzI1LDQuNTI1ICBjLTIuNzcsMi4xOC01LjA1Myw0LjgwNy02Ljc5MSw3Ljc0NmwtMzEuNDI0LTczLjQ2NmMtMS43MzgtNC4wNjMtNi40MzgtNS45NDctMTAuNTAxLTQuMjA5Yy00LjA2MywxLjczOC01Ljk0Nyw2LjQzOS00LjIwOSwxMC41MDEgIGwzMS4zOTksNzMuNDA1Yy0zLjI4OC0wLjc0My02LjcyMS0wLjg5My0xMC4xNjYtMC4zOTZjLTMuMjA0LDAuNDYxLTYuMjQ0LDEuNDUxLTkuMDIyLDIuOTA4bC0yMC45ODYtNDguOTc2ICBjLTEuNzQtNC4wNjEtNi40NDItNS45NDItMTAuNTA0LTQuMjAzYy00LjA2MSwxLjc0LTUuOTQzLDYuNDQzLTQuMjAzLDEwLjUwNGwyMy42MjksNTUuMTQ1ICBjLTUuNzIzLDExLjc2OC0yLjQ5NCwyNi4zNTIsOC4zNjQsMzQuNDc3bDEzLjcwOSwxMC4yNTdjLTcuODY0LDEyLjE3NS01LjA2MywyOC41ODgsNi43MTQsMzcuNDAzbDEzLjcxMiwxMC4yNTggIGMtMy42NTUsNS42ODQtNS4xNjcsMTIuNjM0LTQuMTQ2LDE5LjM2OWMxLjExNCw3LjM1Nyw1LjAwMywxMy43ODUsMTAuOTUxLDE4LjEwMmwzOC45MDIsMjguMjM1ICBjMy45MjcsMy4wMjcsOC41OTksNC45NTEsMTMuNjMyLDUuNTUxYzEuMTE1LDAuMTMzLDIuMjI4LDAuMTk5LDMuMzM0LDAuMTk5YzAuOTA2LDAsMS44MDYtMC4wNTgsMi43MDEtMC4xNDVsMTguMDQ1LDQyLjA5Nkg2MC41NjYgIGMtNC40MTgsMC04LDMuNTgyLTgsOHMzLjU4Miw4LDgsOEgzNTYuNDA3eiBNMjA4LjgzNywyMDAuMTI1bC0wLjMxOSwwLjU1M2wtMC4zMTktMC41NTNjLTEuNjk4LTIuOTQyLTMuODgzLTUuNDk2LTYuNDM5LTcuNTkyICBsMjAuOTM1LTM2LjI2MWMxLjU3My0yLjcyNCw0LjEzMi00LjY3OCw3LjIwNy01LjUwMWMzLjA3NC0wLjgyNCw2LjI2OC0wLjQxMiw4Ljk5LDEuMTYxYzUuNjYzLDMuMjcsNy42MSwxMC41MzcsNC4zNDIsMTYuMTk4ICBsLTEwLjQzOCwxOC4wNzhjLTIuMzQ2LDAuMDIxLTQuNzA3LDAuMzM2LTcuMDM3LDAuOTZDMjE4LjU1NSwxODkuMDk5LDIxMi41NDYsMTkzLjcsMjA4LjgzNywyMDAuMTI1eiBNMTczLjgwNSwxNjguMTMyICBjLTMuMjY5LTUuNjYzLTEuMzIxLTEyLjkzLDQuMzQtMTYuMTk5YzUuNjYzLTMuMjY4LDEyLjkzLTEuMzIxLDE2LjE5OSw0LjM0bDQuOTM3LDguNTUzbC0xMi40MzEsMjEuNTMxICBjLTAuODctMC4wOS0xLjc0LTAuMTQtMi42MDgtMC4xNDhMMTczLjgwNSwxNjguMTMyeiBNMjI5LjksOTguOTJjMy4wNzMtMC44MjQsNi4yNjctMC40MTIsOC45OTIsMS4xNjIgIGM1LjY2MiwzLjI2OSw3LjYxLDEwLjUzNiw0LjM0MSwxNi4xOThsLTEwLjQzNywxOC4wNzdjLTIuMzQ2LDAuMDIxLTQuNzA3LDAuMzM2LTcuMDM3LDAuOTZjLTcuMjAzLDEuOTMtMTMuMjEyLDYuNTMxLTE2LjkyMSwxMi45NTYgIGwtMC4zMTksMC41NTJsLTAuMzE5LTAuNTUzYy0xLjcyMy0yLjk4NC0zLjkyMS01LjUyNS02LjQ0MS03LjU5bDIwLjkzNS0zNi4yNjFDMjI0LjI2NywxMDEuNjk4LDIyNi44MjYsOTkuNzQ0LDIyOS45LDk4LjkyeiAgIE0xOTQuMzQ3LDEwNC40MjdsNC45MzksOC41MzhsLTEyLjQzNCwyMS41MzZjLTAuODY2LTAuMDg4LTEuNzM2LTAuMTM2LTIuNjEtMC4xNDNsLTEwLjQzOC0xOC4wNzggIGMtMy4yNjktNS42NjMtMS4zMjEtMTIuOTI5LDQuMzQxLTE2LjE5OEMxODMuODA4LDk2LjgxMiwxOTEuMDc0LDk4Ljc1OSwxOTQuMzQ3LDEwNC40Mjd6IE0xNzIuNTU1LDIxMS4xNTkgIGMwLjgxOS0zLjE0NywyLjgwNC01Ljc2Niw1LjU4OS03LjM3NGMyLjcyNi0xLjU3Myw1LjkxOC0xLjk4Niw4Ljk5My0xLjE2MmMzLjA3NCwwLjgyNCw1LjYzMiwyLjc3Nyw3LjIwNiw1LjUwMmw0LjkzOCw4LjU1MSAgbC0xMy40OTcsMjMuMzc1bC0xMi4wMzktMjAuMTY5QzE3Mi4xOTEsMjE3LjI4LDE3MS43NjgsMjE0LjE4MiwxNzIuNTU1LDIxMS4xNTl6IE0xOTguOTE2LDI2Mi4wNTNsLTAuMTI3LTAuMjEyICBjLTAuNTY3LTAuOTQ2LTEuMDExLTEuOTc1LTEuMzA2LTMuMDc0Yy0wLjgyNC0zLjA3NC0wLjQxMi02LjI2NywxLjE2MS04Ljk5MWwyNC4wNDktNDEuNjUxYzEuNTczLTIuNzI0LDQuMTMxLTQuNjc3LDcuMjA2LTUuNTAxICBjMS4wMzUtMC4yNzcsMi4wODMtMC40MTUsMy4xMjMtMC40MTVjMi4wNDgsMCw0LjA2MiwwLjUzMyw1Ljg2OSwxLjU3NmMyLjcyNCwxLjU3Myw0LjY3OCw0LjEzMiw1LjUwMiw3LjIwNyAgYzAuODI0LDMuMDc0LDAuNDExLDYuMjY3LTEuMTYxLDguOTkxbC0yNC4wNDcsNDEuNjUxYy0xLjU3MywyLjcyNC00LjEzMiw0LjY3OC03LjIwNiw1LjUwMmMtMS4xMzYsMC4zMDQtMi4yODksMC40MzgtMy40MjgsMC40MDggIGMtMC4wMTEsMC0wLjAyMS0wLjAwMi0wLjAzMS0wLjAwMmMtMC4wMSwwLTAuMDIsMC4wMDEtMC4wMywwLjAwMmMtMS45MjEtMC4wNi0zLjgwMy0wLjU4OS01LjUwMi0xLjU3ICBDMjAxLjMwOSwyNjUuMDA1LDE5OS45MjYsMjYzLjY2LDE5OC45MTYsMjYyLjA1M3ogTTEzMC43NzIsMjM3LjE1MWwtMi40NzQsMjAuNzI5Yy04Ljc2LDMuODMzLTE1LjMyOCwxMi4wNjctMTYuNTQsMjIuMjI3ICBsLTAuMDc2LDAuNjM0bC0wLjUxMS0wLjM4MmMtMi43Mi0yLjAzNS01LjczNS0zLjUyMi04LjkxLTQuNDQxbDQuOTYtNDEuNTc1YzAuNzc1LTYuNDkzLDYuNjktMTEuMTQyLDEzLjE4LTEwLjM3MSAgQzEyNi44OTUsMjI0Ljc0NiwxMzEuNTQ3LDIzMC42NTksMTMwLjc3MiwyMzcuMTUxeiBNOTEuMjU3LDE3OC43ODZjMi41MDEtMS45NjgsNS41OTYtMi44NDksOC43MjMtMi40NzQgIGM2LjQ5MywwLjc3NCwxMS4xNDUsNi42ODYsMTAuMzcxLDEzLjE3OGwtMi40NzQsMjAuNzI5Yy04Ljc1OCwzLjgzMy0xNS4zMjcsMTIuMDY3LTE2LjU0MSwyMi4yMjZsLTAuMDc2LDAuNjM0bC0wLjUxMS0wLjM4MiAgYy0yLjc1OS0yLjA2NS01Ljc4LTMuNTM1LTguOTEtNC40NDFsNC45NjEtNDEuNTc1Qzg3LjE3NCwxODMuNTU4LDg4Ljc1NiwxODAuNzU0LDkxLjI1NywxNzguNzg2eiBNNDQuMTQ1LDIwMC4yMzggIGMxLjg4NC0yLjUxOSw0LjY1Ni00LjE1Niw3LjgwNi00LjYwOWMzLjE1Mi0wLjQ1NCw2LjI3MywwLjMzNCw4LjczOSwyLjE3OWw3Ljk0OCw2LjA0OWwtMi45MzQsMjQuNTg5ICBjLTAuODMxLDAuMjYtMS42NSwwLjU1OS0yLjQ1NSwwLjg5N2wtMTYuNzE0LTEyLjUwNUM0MS4yOTksMjEyLjkyLDQwLjIyNywyMDUuNDc1LDQ0LjE0NSwyMDAuMjM4eiBNNjQuNTY3LDI0Ny44OTggIGMzLjkxOC01LjIzNCwxMS4zNjItNi4zMDgsMTYuNTk4LTIuMzkxbDcuOTA3LDUuOTE2bC0yLjk0NiwyNC42ODdjLTAuODM1LDAuMjYtMS42NTQsMC41NTYtMi40NTUsMC44OTFsLTE2LjcxMy0xMi41MDMgIEM2MS43MjMsMjYwLjU4LDYwLjY1MSwyNTMuMTMzLDY0LjU2NywyNDcuODk4eiBNODIuNzU2LDMwNC41NGMtMC40ODctMy4yMTUsMC4zMDYtNi40MDQsMi4yMzQtOC45ODEgIGMxLjg4NC0yLjUxOSw0LjY1Ny00LjE1Niw3LjgwNy00LjYwOWMzLjE0OS0wLjQ1NSw2LjI3MiwwLjMzNCw4Ljc5MiwyLjIxOWw3LjkwNiw1LjkxNWwtMy4xOTgsMjYuODAybC0xOS4wMTEtMTMuNzk4ICBDODQuODMyLDMxMC4zMDgsODMuMjI0LDMwNy42MjcsODIuNzU2LDMwNC41NHogTTEyNy4wMzEsMzQwLjkzNWwtMC4xOTQtMC4xNDFjLTAuODk1LTAuNjQ3LTEuNzEtMS40MTgtMi40MTQtMi4zMTQgIGMtMS45NjgtMi41MDEtMi44NDctNS41OTktMi40NzQtOC43MjJsNS42OTktNDcuNzU2YzAuNzE3LTYuMDE3LDUuODQ5LTEwLjQ1MywxMS43NjYtMTAuNDUzYzAuNDY2LDAsMC45MzgsMC4wMjgsMS40MTIsMC4wODQgIGM2LjQ5MywwLjc3NSwxMS4xNDYsNi42ODcsMTAuMzcxLDEzLjE3OGwtNS42OTksNDcuNzU2Yy0wLjM3MywzLjEyNC0xLjk1NSw1LjkyOC00LjQ1Niw3Ljg5NmMtMC45MjcsMC43MjktMS45MzcsMS4zMDctMi45OTksMS43MjggIGMtMC4wMSwwLjAwNC0wLjAyLDAuMDA3LTAuMDI5LDAuMDExYy0wLjAwNywwLjAwMy0wLjAxNCwwLjAwNy0wLjAyMSwwLjAxYy0xLjc5LDAuNzAxLTMuNzI3LDAuOTU1LTUuNjc0LDAuNzIzICBDMTMwLjM5MywzNDIuNzA3LDEyOC41OTIsMzQyLjAxNSwxMjcuMDMxLDM0MC45MzV6IE0zMTYuOTkzLDE3Ni4zMTNjMy4xMi0wLjM3NSw2LjIyMSwwLjUwNSw4LjcyMiwyLjQ3NCAgYzIuNTAxLDEuOTY4LDQuMDgzLDQuNzcxLDQuNDU2LDcuODk1bDQuOTYsNDEuNTc1Yy0zLjE3NSwwLjkxOS02LjE5LDIuNDA2LTguOTEsNC40NDFsLTAuNTEsMC4zODJsLTAuMDc2LTAuNjM0ICBjLTEuMjEzLTEwLjE1OC03Ljc4Mi0xOC4zOTItMTYuNTM5LTIyLjIyNWwtMi40NzQtMjAuNzI4QzMwNS44NDgsMTgyLjk5OSwzMTAuNSwxNzcuMDg2LDMxNi45OTMsMTc2LjMxM3ogTTI5Ni41NywyMjMuOTcyICBjMS4yMTYtMC4xNDUsMi40MTItMC4xLDMuNTU4LDAuMTEyYzAuMDYsMC4wMTMsMC4xMTksMC4wMzEsMC4xOCwwLjA0MmM0Ljg4NiwwLjk4NSw4LjgxOSw1LjAwNCw5LjQ0MSwxMC4yMTVsNC45NjEsNDEuNTc2ICBjLTMuMTc2LDAuOTE5LTYuMTksMi40MDYtOC45MSw0LjQ0MWwtMC41MSwwLjM4MmwtMC4wNzYtMC42MzNjLTEuMjExLTEwLjE2LTcuNzgtMTguMzk2LTE2LjU0MS0yMi4yMjhsLTIuNDczLTIwLjcyNyAgQzI4NS40MjYsMjMwLjY1OSwyOTAuMDc4LDIyNC43NDYsMjk2LjU3LDIyMy45NzJ6IE0yNzguOTcxLDM0Mi4yMWMtMC4wMDUtMC4wMDItMC4wMS0wLjAwNS0wLjAxNi0wLjAwOCAgYy0wLjAwNC0wLjAwMi0wLjAwOS0wLjAwMy0wLjAxMy0wLjAwNWMtMS4wNjYtMC40MjItMi4wOC0xLjAwMi0zLjAxLTEuNzM0Yy0yLjUwMS0xLjk2OC00LjA4My00Ljc3Mi00LjQ1Ni03Ljg5NmMwLDAsMCwwLDAsMCAgbC01LjY5OS00Ny43NTZjLTAuNzc0LTYuNDkyLDMuODc4LTEyLjQwMywxMC4zNjktMTMuMTc4YzAuNDc1LTAuMDU3LDAuOTQ2LTAuMDg0LDEuNDEzLTAuMDg0YzUuOTE3LDAsMTEuMDQ5LDQuNDM3LDExLjc2NywxMC40NTQgIGw1LjY5OCw0Ny43NTdjMC4zNzMsMy4xMjMtMC41MDYsNi4yMi0yLjQ3NCw4LjcyMWMtMC43MDQsMC44OTYtMS41MiwxLjY2OC0yLjQxNSwyLjMxNGwtMC4xOTMsMC4xNCAgYy0xLjU2MSwxLjA4LTMuMzYzLDEuNzcyLTUuMjg4LDIuMDAyQzI4Mi43MDMsMzQzLjE2OSwyODAuNzYyLDM0Mi45MTQsMjc4Ljk3MSwzNDIuMjF6IE0zMzQuMjE2LDMwNC41MzkgIGMtMC40NjgsMy4wODgtMi4wNzYsNS43NjktNC41Myw3LjU0OWwtMTkuMDEsMTMuNzk4bC0zLjE5OC0yNi44MDFsNy45MDctNS45MTZjMi41Mi0xLjg4NSw1LjY0MS0yLjY3NCw4Ljc5MS0yLjIxOSAgYzMuMTUsMC40NTQsNS45MjIsMi4wOTEsNy44MDcsNC42MUMzMzMuOTEsMjk4LjEzNSwzMzQuNzAzLDMwMS4zMjQsMzM0LjIxNiwzMDQuNTM5eiBNMzUwLjAxNSwyNjQuNDk3bC0xNi43MTQsMTIuNTA1ICBjLTAuODAxLTAuMzM1LTEuNjItMC42MzEtMi40NTUtMC44OTFsLTIuOTQ2LTI0LjY4N2w3LjkwNi01LjkxNmMyLjUxOS0xLjg4NSw1LjYzOS0yLjY3NCw4Ljc5MS0yLjIxOSAgYzMuMTUsMC40NTQsNS45MjIsMi4wOSw3LjgwNyw0LjYwOUMzNTYuMzIyLDI1My4xMzQsMzU1LjI1LDI2MC41OCwzNTAuMDE1LDI2NC40OTd6IE0zNzUuMDQ3LDIwOS4wMyAgYy0wLjQ1NCwzLjE1LTIuMDkxLDUuOTIzLTQuNjA5LDcuODA3bC0xNi43MTMsMTIuNTA0Yy0wLjgwMS0wLjMzNS0xLjYyMS0wLjYzMS0yLjQ1Ny0wLjg5MWwtMi45NDEtMjQuNjQ3bDcuOTAzLTUuOTU2ICBjMi41MTktMS44ODQsNS42MzktMi42NzEsOC43OTEtMi4yMTljMy4xNSwwLjQ1NCw1LjkyMiwyLjA5MSw3LjgwNyw0LjYxMUMzNzQuNzEzLDIwMi43NTgsMzc1LjUwMSwyMDUuODc5LDM3NS4wNDcsMjA5LjAzeiIgZmlsbD0iI0ZGRkZGRiIvPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8L3N2Zz4K)}';
        $style = $style . 'div.beifuss {margin:8px;display: inline-block;width: 32px;height: 32px;background-image: url(data:image/svg+xml;utf8;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/Pgo8IS0tIEdlbmVyYXRvcjogQWRvYmUgSWxsdXN0cmF0b3IgMTcuMS4wLCBTVkcgRXhwb3J0IFBsdWctSW4gLiBTVkcgVmVyc2lvbjogNi4wMCBCdWlsZCAwKSAgLS0+CjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+CjxzdmcgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgdmVyc2lvbj0iMS4xIiBpZD0iQ2FwYV8xIiB4PSIwcHgiIHk9IjBweCIgdmlld0JveD0iMCAwIDQ3MS44OTggNDcxLjg5OCIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAwIDAgNDcxLjg5OCA0NzEuODk4OyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSIgd2lkdGg9IjMycHgiIGhlaWdodD0iMzJweCI+CjxwYXRoIGlkPSJYTUxJRF80NzhfIiBkPSJNMzIzLjQwNSwzMDcuMTYzYy0wLjM5Ny0xLjg4LTEuMTA3LTMuNi0yLjEyNS01LjE0Yy0yLjMxOC0zLjUxNi01Ljk5LTUuNzMxLTEwLjYxNy02LjQwNyAgYy02LjM4Ny0wLjkzLTE0LjU4MSwxLjI2NS0yMi45NjksNS45MTJjLTEuNzUyLTYuMTk5LTQuODYtMTEuNDk2LTkuMTQ4LTE1LjQwOGMtNS4yMzgtNC43NzgtMTEuNzYtNy4wODMtMTguODY2LTYuNjU1ICBjLTMuMDI2LDAuMTgxLTYuMDU1LDAuODU5LTkuMDMyLDIuMDE5Yy0zLjA5NS0wLjc5OC02LjE4Mi0xLjExLTkuMjEyLTAuOTI5Yy03LjgxMiwwLjQ2Ni0xNC42ODIsNC4yMzktMTkuMzQ2LDEwLjYyNCAgYy02LjI5Myw4LjYxNi04LjAyMSwyMC45NDctNC43MzksMzMuODMyYzEuNDQ0LDUuNjcyLDMuNzg4LDExLjA2Miw2Ljc5MSwxNS45MDZsLTE4LjkyMiwxNS40NjN2LTgyLjU3MyAgYzAuMDMxLTAuMjgyLDAtMi4yMjUsMC0yLjIyNWMxLjI1MSwwLjEwMiwyLjUxMiwwLjE1OCwzLjc4MSwwLjE1OGMxNS4zMiwwLDMxLjY1My03LjE5LDQyLjg2OS0xNS44NDUgIGMxMi41OTctOS43MjEsMTkuNTgxLTIxLjU5LDE5LjE2My0zMi41NjRjLTAuMTA1LTIuNzctMS45NTYtNS4xNjgtNC42MDktNS45NzNjLTMuNjA2LTEuMDk0LTcuNTIzLTEuNjM4LTExLjYyNC0xLjY3OCAgYzAuNjM0LTQuNzY3LDAuNzEzLTkuNjIxLDAuMTM0LTEzLjU1Yy0wLjQwNC0yLjc0Mi0yLjUwNC00LjkyNy01LjIyOS01LjQzOWMtOC45NzItMS42ODktMTguNzU2LDEuNTk3LTI4LjI4OSw5LjUwMyAgYy02LjA4NSw1LjA0Ni0xMS42MTksMTEuNTYxLTE2LjE5NiwxOC43MjN2LTI5LjkwOGM5LjIyNy0wLjYwMiwxOS4zNzYtNS40MDgsMjguODcxLTEzLjg1NCAgYzEzLjc0OS0xMi4yMzEsMjYuNjg4LTMzLjIwMiwyNC42MDMtNTIuNDgyYy0wLjI5OC0yLjc1Ni0yLjMxMi01LjAyMS01LjAxNS01LjYzOGMtMTAuNDcxLTIuMzkxLTIyLjYzMSwxLjY3MS0zNC4yNTIsMTEuNDM1ICBjLTUuMzg2LDQuNTI1LTEwLjE4OCw5Ljg2MS0xNC4yMDcsMTUuNTk2di0xOS45OTJjOS41OS0yLjU4OCwxOS4wNzUtMTAuMzMzLDI2LjcyLTIyLjA0OSAgYzEwLjAwNi0xNS4zMzQsMTYuOTg2LTM4Ljc1NywxMC4wMjUtNTYuNTIxYy0xLjAxMS0yLjU4My0zLjU3Ni00LjIzMi02LjMxOS00LjEyNGMtNC45NDksMC4yMDQtOS44NjQsMS44MTgtMTQuNTc4LDQuNzExICBjMC45MTktMTcuMjk2LTMuNjQxLTM4LjA1NC0xNi41NzUtNTAuMzA3Yy0yLjAxMy0xLjkwNi01LjAxNS0yLjMyOS03LjQ3My0xLjA0NmMtOS44ODgsNS4xNDktMTYuNTQxLDE2LjYxLTE4LjczNSwzMi4yNzMgIGMtMi40MzgsMTcuNDAzLDEuMDI0LDQwLjU1MSwxMy45MzYsNTQuNjU4djQ5LjA3NWMtOC4wNzItMTEuMjc5LTE4LjcyLTE5LjYyNy0zMC40NzktMjAuNDE4Yy0yLjc3NC0wLjE4Ni01LjM0NywxLjQwMy02LjQyNSwzLjk1NyAgYy0xLjY5Nyw0LjAxOS0yLjc4Niw4LjQ1LTMuMjcyLDEzLjIzOWMtNC41MTctMS4xNzMtOS4wNzUtMS43NDMtMTMuNTY3LTEuNTU5Yy0yLjc3MSwwLjExNC01LjE2MywxLjk3My01Ljk1OSw0LjYyOSAgYy0zLjQ0NSwxMS41LDAuNzMsMjYuMDE0LDExLjQ1NywzOS44MjFjMTAuOTI5LDE0LjA2OCwyOS40NDQsMjcuOTI5LDQ4LjI0NSwzMC4xMjJ2MjguODc1Yy0zLjQ2Mi00Ljc4OC03LjQ4OC05LjAzNy0xMS45NDItMTIuNDM3ICBjLTcuMDQzLTUuMzc2LTE0LjY4Mi04LjI2MS0yMi4xNi04LjQ5NGMtMC4xNDgtMC4xOTctMC4yOTUtMC40MDUtMC40NDQtMC41OTZjLTcuNjQ3LTkuODc4LTE3LjU4Ni0xNi4wNTEtMjYuNTg2LTE2LjUxMyAgYy01LjUwNS0wLjI3Ni0xMC40ODYsMS42MTYtMTQuMDM2LDUuMzRjLTEuNDA2LDEuNDc0LTIuNTYsMy4xODgtMy40NDgsNS4xMTljLTEuODg2LDAuOTgtMy41NDIsMi4yMTMtNC45NDYsMy42ODcgIGMtMy41NTEsMy43MjctNS4yMDYsOC43OTgtNC42NiwxNC4yNzljMC44OTUsOC45NjcsNy41MzgsMTguNTk4LDE3Ljc3MywyNS43NjRjMC4yMTQsMC4xNSwwLjQ0LDAuMjk0LDAuNjU4LDAuNDQyICBjMC44NTIsOS42MjQsNi4yNjEsMTkuMjQ2LDE1LjYxNSwyNy4zMjNjMTIuNiwxMC44NzksMjkuNDIyLDE2LjkxOSw0NS4wODEsMTYuOTE5YzMuMDksMCw2LjEzNi0wLjIzNiw5LjA5NS0wLjcxNnY4Mi41NzkgIGMtMC4wNDYsMC44OTIsMCwxMS4zMjgsMCwxMS4zMjhsLTMuOTQzLTQuNjUzYzAtMC41Ny0wLjAxMS0xLjE0Mi0wLjA0Ny0xLjcxN2MtMC40ODktNy42OTYtNC4yNTQtMTUuNDg5LTEwLjUyNS0yMi4wNDggIGMxLjYwNC0yLjQyMywzLjA0Mi00Ljk4OSw0LjI4Mi03LjY3NmM1LjU2OS0xMi4wNzMsNi4xMTItMjQuNTEzLDEuNDg5LTM0LjEzYy0zLjQyNi03LjEyNi05LjQ5Ni0xMi4wODUtMTcuMDkzLTEzLjk2MyAgYy0yLjk0Ni0wLjcyOS02LjAzNi0wLjk4Mi05LjIyNy0wLjc2MWMtMi43MTYtMS42ODItNS41NzEtMi44OTgtOC41MTUtMy42MjZjLTcuMTkxLTEuNzgxLTE0LjUzNi0wLjQ2MS0yMC42ODQsMy43MTQgIGMtNS4zNjUsMy42NDMtOS42MDIsOS4yOS0xMi4zMDEsMTYuMTY1Yy03Ljc2OS02LjY3NC0xNS44MDctMTAuNTc5LTIyLjY0OC0xMC43NzljLTQuNjI3LTAuMTI5LTguNTU1LDEuMzc4LTExLjQ0LDQuMzc3ICBjLTEuMjc5LDEuMzMxLTIuMjg5LDIuODkzLTMuMDIxLDQuNjY3Yy0xLjc0NywwLjgwMi0zLjI2OSwxLjg3Mi00LjU0NiwzLjIwMmMtMi45MiwzLjAzNS00LjI3NSw3LjEwMy0zLjkxOCwxMS43NjUgIGMwLjQ5Myw2LjQzNyw0LjQzNCwxMy45NDgsMTAuODEyLDIxLjExYy01LjY2MSwzLjA3MS0xMC4xNDUsNy4yNjYtMTMuMDIsMTIuMzA5Yy0zLjUxMSw2LjE2LTQuMzIzLDEzLjAyOS0yLjM1LDE5Ljg2NSAgYzAuODQyLDIuOTE0LDIuMTY5LDUuNzIsMy45NTYsOC4zNzFjLTAuMDk5LDMuMTk4LDAuMjc1LDYuMjgsMS4xMTcsOS4xOTJjMi4xNzIsNy41MTgsNy4zNjIsMTMuMzkxLDE0LjYxNiwxNi41MzcgIGMzLjk4MywxLjcyOSw4LjQsMi41ODMsMTMuMDM3LDIuNTgzYzYuNzU3LDAsMTMuOTgxLTEuODE2LDIxLjAwNy01LjM5NGMyLjYyNS0xLjMzNiw1LjEzMS0yLjg3OSw3LjQ5MS00LjU5ICBjNi44MDIsNi4wMjEsMTQuNzQxLDkuNDg3LDIyLjQ1Nyw5LjY3N2MwLjIyMiwwLjAwNSwwLjQ0MywwLjAwOCwwLjY2NCwwLjAwOGMwLjM1MiwwLDAuNzAyLTAuMDEyLDEuMDUyLTAuMDI2bDEzLjA4NSwxMC4yNDMgIGM0LjQ3OCwzLjUwNCwxMC4wNDksNS4zNDQsMTUuNzY0LDUuMzQ0YzIuNDQxLDAsNC45MTEtMC4zMzYsNy4zMzEtMS4wMjJjMS43ODQtMC40MjgsMy40OTctMS4wOTUsNS4xMTgtMS45NTR2MjcuNjE3ICBjLTAuMDksMTAuMzczLDguODI3LDguOTU2LDExLjY1OSw1LjUzNWw0My43NzktNTcuMTI2YzIuMTg0LTIuODQ5LDEuNjQ0LTYuOTI5LTEuMjA1LTkuMTEzYy0yLjg1LTIuMTg0LTYuOTMtMS42NDMtOS4xMTMsMS4yMDYgIGwtMzIuMTIsNDEuOTEzdi00NC45MzZjMC4yMDYsMC4wOTksMC40MDYsMC4yMDgsMC42MTUsMC4zMDFjMy43NDcsMS44NTEsNy44MDEsMi43NzcsMTEuODE0LDIuNzc3ICBjNC4wNTMsMCw4LjA2NC0wLjk0NCwxMS42NzgtMi44MzJsMjUuODU0LTEzLjUwNmMzLjI3NCw0LjYzMiw3LjMxOSw4Ljg4NywxMi4wMDMsMTIuNDc0YzguMjg1LDYuMzQ2LDE3LjQ2OSw5LjcyMiwyNi4wNjMsOS43MjEgIGMyLjM1NiwwLDQuNjctMC4yNTQsNi45MDctMC43N2M3LjcwNC0xLjc3NiwxMy44NzQtNi42MDgsMTcuMzc0LTEzLjYwNWMxLjM1OS0yLjcxNywyLjI4OC01LjY3OSwyLjc3MS04LjgzNSAgYzIuMjM3LTIuMjgxLDQuMDUyLTQuNzk5LDUuNDA3LTcuNTEyYzMuNTAyLTYuOTk5LDMuNjctMTQuODM1LDAuNDczLTIyLjA2N2MtMi41MDYtNS42NjgtNi44NzgtMTAuNjIxLTEyLjYwNC0xNC40ODkgIGM4LjIwOC02LjExLDEzLjc3OS0xMy4wODksMTUuNDc2LTE5LjcxNWMxLjE0Mi00LjQ1OCwwLjUzNC04LjY0OS0xLjc1Ny0xMi4xMjNDMzI2LjI3NywzMDkuNTk1LDMyNC45NzUsMzA4LjI2NywzMjMuNDA1LDMwNy4xNjN6ICAgTTE2Mi42NTYsNDAwLjk0NmMtMC44OTgsMi4yNzgtMi4xMTMsNC4yMjMtMy42MDksNS43OGMtMi41ODMsMi42ODUtNS43ODksMy45OTItOS41MjEsMy45MDcgIGMtNC4zMTMtMC4xMDYtOC45MDgtMi4wNzQtMTMuMTI4LTUuNTI3YzQuMTk1LTQuODM3LDcuNDQ4LTEwLjI0Nyw5LjQ1MS0xNS44MTFsMi43NTEsMi42NDdjMS41OTMsMS41MzIsNC4xMjUsMS40ODQsNS42NTYtMC4xMDkgIGMxLjUzMS0xLjU5MiwxLjQ4Mi00LjEyNS0wLjEwOS01LjY1NmwtMy45ODctMy44MzZjNi44MjktMi4wMDIsMTMuNDI3LTUuOTQ1LDE5LjExMi0xMS4zMDNjMy41ODMsNC4wNjksNS43MSw4LjU2Miw1Ljk4MiwxMi44NDcgIGMwLjIzNywzLjcyOS0wLjk1Miw2Ljk4My0zLjUzNSw5LjY2OGMtMS40OTIsMS41NTEtMy4zODgsMi44MzktNS42MzQsMy44MjhDMTY0LjUxNiwzOTguMDcyLDE2My4yODUsMzk5LjM1MiwxNjIuNjU2LDQwMC45NDZ6ICAgTTgzLjM3LDMzMS4yMTRjMC4zMDEtMC4zMTMsMC45NDctMC41OSwxLjc3Mi0wLjc1OWMyLjYzNy0wLjU0Miw0LjY2Ny0yLjY1Myw1LjEwNC01LjMwOWMwLjEzNy0wLjgzMSwwLjM5LTEuNDg4LDAuNjktMS44MDEgIGMwLjMwOC0wLjMyLDAuOTU3LTAuMzk2LDEuNTA4LTAuMzk2YzAuMDYzLDAsMC4xMjQsMC4wMDEsMC4xODQsMC4wMDNjNC4xMjksMC4xMiwxMS45MDQsNC4xNjcsMTkuODE2LDEyLjczMiAgYy0wLjAxLDAuMTQ5LDAuMzIzLDcuNzc1LDEuMTY2LDExLjc3OWwtNS42LTMuOTM0Yy0xLjgxMS0xLjI3MS00LjMwMi0wLjgzMy01LjU3MywwLjk3NGMtMS4yNywxLjgwOC0wLjgzNCw0LjMwMywwLjk3NCw1LjU3MiAgbDEwLjYzNCw3LjQ3MWwwLjk5OSw0LjU3MmMtNC43NjMtMS4wNDItMTAuMDg1LTMuMzIxLTE1LjQ1Mi03LjQxNmMtMTAuODYyLTguMjg5LTE2LjE5My0xNy4wNzEtMTYuNTQ5LTIxLjcyNSAgQzgyLjk5OCwzMzIuMzc1LDgzLjAxNiwzMzEuNTgyLDgzLjM3LDMzMS4yMTR6IE0xMTMuMzYsNDA2Ljk1N2MtOC4yNTksNC4yMDUtMTYuODQ4LDUuMTI2LTIyLjk3MywyLjQ3ICBjLTMuNzM3LTEuNjIyLTYuMTkzLTQuMzg3LTcuMzAxLTguMjIxYy0wLjU5OC0yLjA2Ni0wLjc2Ni00LjM1NC0wLjQ5OS02Ljc5OGMwLjE4Ni0xLjcwMS0wLjMwOC0zLjQwNS0xLjM3MS00Ljc0NSAgYy0xLjUyNy0xLjkyNS0yLjYwNS0zLjk1LTMuMjAyLTYuMDE3Yy0wLjk5MS0zLjQzMy0wLjYwNC02LjczOCwxLjE1NC05LjgyMmMyLjIzNi0zLjkyMyw2LjU1LTcuMTY2LDEyLjA2OC05LjE2ICBjMC4xNTksMC4xMjMsMC4zMSwwLjI1LDAuNDcsMC4zNzJjMTAuMzgyLDcuOTIyLDIwLjA1MiwxMC44MTksMjguNzE0LDEwLjEzNGMxMC41NzktMC44MzcsNy44MzMtOC4zMzMsNi4xNjctMTYuNzUgIGMtMS4xMjgtNS42OTgtMS41NzUtMTQuMjQzLTEuMTg2LTIxLjU5OWMwLjA0Ny0wLjg4NSwwLjI5OC0zLjI2LDAuMzE0LTMuMzgxYzEuMTA4LTguMTQ2LDQuNTg3LTE0Ljc0OSw5LjU0NC0xOC4xMTUgIGMzLjE0Mi0yLjEzNCw2LjU5Mi0yLjc1OCwxMC4yNi0xLjg1YzIuMDkxLDAuNTE3LDQuMTU0LDEuNTE1LDYuMTMzLDIuOTY1YzEuMzgxLDEuMDEzLDMuMTA0LDEuNDM4LDQuODAxLDEuMTg3ICBjMi40MjMtMC4zNjIsNC43MTUtMC4yODMsNi44MSwwLjIzNWMzLjg3MiwwLjk1OCw2LjczLDMuMzA0LDguNDk2LDYuOTc2YzIuODk0LDYuMDIsMi4zMDQsMTQuNjM3LTEuNTc4LDIzLjA1MyAgYy01LjE1NiwxMS4xNzktMTUuMzMxLDIwLjA0NS0yNS4xNTYsMjIuMzM2bDguNTIxLTM0LjM3MWMwLjUzMS0yLjE0NC0wLjc3Ni00LjMxMy0yLjkyLTQuODQ1Yy0yLjE0Ni0wLjUzMi00LjMxMywwLjc3Ni00Ljg0NiwyLjkyICBsLTEwLjA5Miw0MC43MTJjLTAuMDE4LDAuMDQxLTAuMDM0LDAuMDgzLTAuMDUyLDAuMTI0bC0zMS45NTcsMTkuNzA4Yy0xLjg4MSwxLjE2LTIuNDY1LDMuNjI0LTEuMzA1LDUuNTA0ICBjMC43NTYsMS4yMjYsMi4wNjYsMS45MDEsMy40MDgsMS45MDFjMC43MTYsMCwxLjQ0MS0wLjE5MiwyLjA5Ni0wLjU5NmwyNS41MjktMTUuNzQ0ICBDMTMwLjE4MSwzOTMuOTcxLDEyMi42OTcsNDAyLjIwNiwxMTMuMzYsNDA2Ljk1N3ogTTE5MS41NzYsNDE3LjM5OGMtMC4wMjksMC4xMTctMC4wNTUsMC4yMzUtMC4wNzcsMC4zNTQgIGMtMC4zMjksMS43MjQtMS4yLDMuMzYyLTIuNTExLDQuNzI2bC0wLjM2MywwLjM3N2MtMS4zMTcsMS4zNjktMi45MTksMi4zMDItNC42MzIsMi42OThjLTAuMTE3LDAuMDI3LTAuMjMzLDAuMDU4LTAuMzQ5LDAuMDkxICBjLTQuMDY3LDEuMTgyLTguNTIxLDAuNDA1LTExLjYyNi0yLjAyNmwtNi42MjUtNS4xODdjMS4wNjQtMC44MTEsMi4wNzYtMS43MDgsMy4wMjUtMi42OTVjMi4xMDQtMi4xOSwzLjg3MS00Ljc0MSw1LjI3MS03LjYwOCAgYzIuODE1LTEuNTEyLDUuMjk3LTMuMzc1LDcuMzk5LTUuNTYyYzAuOTQ4LTAuOTg2LDEuODA3LTIuMDI5LDIuNTc1LTMuMTIzbDUuNDM4LDYuNDE4ICBDMTkxLjY0OSw0MDguODY5LDE5Mi41OTgsNDEzLjI4OSwxOTEuNTc2LDQxNy4zOTh6IE0yNTcuMzAzLDIyOC43ODRjLTEuNzQxLDUuNDgtNi40NTIsMTEuNDc1LTEzLjM3NiwxNi44MTggIGMtMTEuNzMxLDkuMDUzLTI2LjQxMywxMy45NjYtMzcuNzY2LDEzLjAxMmMyLjAxNy01LjgxMiw3LjE4MS0xMi4xMDUsMTQuNjQ1LTE3LjY0MiAgQzIzMi40NTMsMjMyLjMzMywyNDYuMzkyLDIyNy43OTIsMjU3LjMwMywyMjguNzg0eiBNMjI5LjcxNCwyMTYuMTk5YzQuMzc4LTMuNjMxLDguNjk5LTUuODk2LDEyLjU4OS02LjY1MSAgYy0wLjA5NSwyLjUwNi0wLjQyNCw1LjE4MS0wLjk0MSw3LjY3NmMtNi44NjYsMS41ODUtMTMuODcsNC4zNjItMjAuNTM1LDguMjEzQzIyMy41ODQsMjIxLjk0OSwyMjYuNTc5LDIxOC43OTgsMjI5LjcxNCwyMTYuMTk5eiAgIE0yMjcuNzg4LDE0NC40MjFjNi4yNjUtNS4yNjQsMTIuNTcxLTguMzY0LDE3Ljk5OS04Ljk1MmMtMS4wNjEsMTEuNy04Ljg1NCwyNS43NTUtMjAuMzM4LDM1Ljk3ICBjLTYuODQ3LDYuMDkxLTEzLjk1Miw5Ljc3Ny0xOS45NjUsMTAuNTA5QzIwNy4xOTksMTY5LjM0NiwyMTUuODk5LDE1NC40MDksMjI3Ljc4OCwxNDQuNDIxeiBNMjMxLjI3Nyw2MS40NDggIGMxLjk0MSwxMS4zNDgtMS45MDMsMjYuNzE2LTEwLjIyNiwzOS40NzFjLTQuODIzLDcuMzkyLTEwLjQyMywxMi43NDUtMTUuODMzLDE1LjM0NVY4OC42MTFjMy45OTItMi45MzQsNy4zMy03LjAzOCw5LjkwNy0xMi4xMzUgIEMyMjAuMTE5LDY4LjgyOSwyMjUuNzksNjMuNTkzLDIzMS4yNzcsNjEuNDQ4eiBNMTkxLjE1NywzNC44MTJjMS4yLTguNTY1LDQuMDA0LTE1LjM4LDcuOTE5LTE5LjUwOCAgYzcuMTA1LDkuOTA5LDEwLjYyNSwyNi4yOTcsOC41MDcsNDEuNDE1Yy0wLjczNCw1LjIzNS0yLjA2OSw5LjgxMi0zLjg4OSwxMy41MjdjLTAuMDkyLDAuMTY2LTAuMTc2LDAuMzM0LTAuMjUzLDAuNTA0ICBjLTEuMSwyLjE0NS0yLjM2NSwzLjk4OS0zLjc3Nyw1LjQ3OEMxOTIuNTU4LDY2LjMxOSwxODkuMDQsNDkuOTMsMTkxLjE1NywzNC44MTJ6IE0xNjUuNjgyLDEzMC40MDMgIGM4LjcwMiwzLjM0NiwxNy42NjksMTMuNzUyLDIzLjM3OSwyNy4wMDhjLTYuODI1LTcuNjIyLTE1LjI0Mi0xNC4zNDItMjQuMjktMTguOTY4ICBDMTY0LjgyNywxMzUuNTc4LDE2NS4xMjEsMTMyLjg4NSwxNjUuNjgyLDEzMC40MDN6IE0xNTQuMjQsMTY4LjQzNWMtNi4zNTQtOC4xOC05Ljg0OS0xNi40OTgtOS45MTUtMjMuMjM4ICBjMy40OTQsMC40NTQsNy4wNDMsMS40NjgsMTAuNTUsMi45MTJjMC41NzQsMC4zNjMsMS4yMDMsMC42NDMsMS44NzQsMC44MTNjMTYuODk5LDcuNzM0LDMyLjM5NiwyNS4yNzYsMzUuNDY5LDM5Ljg0OXY0LjYxMiAgQzE3OS4zOTYsMTkxLjI4MSwxNjQuNTc0LDE4MS43MzYsMTU0LjI0LDE2OC40MzV6IE0xOTIuMjE5LDI3Mi4zNDN2OS4wNzJjLTYuODgyLTAuMTgxLTE0LjMyOC00LjA3NC0yMS4wNzYtMTEuMTY4ICBjLTExLjMzOC0xMS45Mi0xNi45OTYtMjguOTUxLTE0LjU1Ni00Mi44MDNjNi4zMTItMC4yMTUsMTEuOTQxLDIuOTE0LDE1LjgwMiw1Ljg1OSAgQzE4My41NTcsMjQxLjgyOSwxOTEuNjEzLDI1Ny43NjksMTkyLjIxOSwyNzIuMzQzeiBNMTE2LjkzMiwyMjQuNTAyYy0wLjIyOS0yLjI5NiwwLjUzOC0zLjM5NSwxLjEzNS00LjAyMSAgYzAuNjUxLTAuNjg0LDEuNTc3LTEuMjM0LDIuNzUxLTEuNjM1YzIuMDA2LTAuNjg3LDMuNTUxLTIuMzA5LDQuMTM5LTQuMzQ1YzAuMzQ0LTEuMTg4LDAuODQ5LTIuMTM3LDEuNTAzLTIuODIzICBjMC41OTctMC42MjcsMS42NDYtMS40NDIsMy45NjEtMS4zMjhjMy40NDYsMC4xNzcsOS4xNzEsMi42NzEsMTQuODM3LDguOTQyYy0wLjA2MiwwLjE1OC0wLjEzLDAuMzEyLTAuMTgsMC40NzYgIGMtMS4wMjYsMy4zNjUtMS42NSw2Ljc3Mi0xLjkzMSwxMC4xNzhsLTguNjMxLTguNTcxYy0xLjU2OC0xLjU1Ni00LjEwMS0xLjU0OC01LjY1NiwwLjAyYy0xLjU1NywxLjU2OC0xLjU0OCw0LjEwMSwwLjAyLDUuNjU3ICBsOS4yMDQsOS4xNDFjLTMuODkzLDAuNDM5LTcuNjEsMS4yNy0xMS4wNCwyLjVjLTAuMTYxLDAuMDU4LTAuMzEzLDAuMTMzLTAuNDY3LDAuMjAzICBDMTIwLjA0MywyMzMuNTM3LDExNy4yNzQsMjI3LjkzNiwxMTYuOTMyLDIyNC41MDJ6IE0xMzUuMzAxLDI0OS44MDhjMi45OS0wLjY3Nyw2LjIyNS0wLjk5OSw5LjYtMC45NzQgIGMyLjk3MiwxMS40NjIsOS4wNjksMjEuOTMxLDE2LjA4MywyOS41NzFjLTUuNDg3LTIuMzg4LTEwLjQ2My01LjQ4Ni0xNC40NDQtOC45MjNDMTM5LjQ0MSwyNjMuMzUzLDEzNS40OTQsMjU2LjM2NSwxMzUuMzAxLDI0OS44MDggIHogTTIyMy4zMDcsMzkwLjA0Yy0zLjQ5NSwxLjgyNS04LjAxNiwxLjc4LTExLjc5OC0wLjExOWMtMC4xMDgtMC4wNTQtMC4yMTgtMC4xMDUtMC4zMjgtMC4xNTRjLTEuNjExLTAuNy0zLjAxNy0xLjkwOC00LjA2Ni0zLjUgIGwtMC4yNzktMC40MjFjLTEuMDQ4LTEuNTktMS42MDYtMy4zNTktMS42MTYtNS4xMTV2LTIuMDkzYzAuMjM3LTMuNjA3LDEuODg0LTcuMDExLDQuNTItOS4xNjVsMjIuNDkxLTE4LjM3OCAgYzQuMDkyLDQuMDQzLDguNzA5LDcuMjU0LDEzLjU3NSw5LjMyN2MtMC4wMzQsNS4zNDIsMS4wNzMsMTAuODU5LDMuMTc0LDE2LjIwN0wyMjMuMzA3LDM5MC4wNHogTTMxNC4wNzksMzczLjcxNCAgYy0wLjk2MywxLjkyNi0yLjM4OSwzLjcyLTQuMjM3LDUuMzMxYy0xLjI5MiwxLjEyNS0yLjA4NywyLjcxNS0yLjIxMyw0LjQyM2MtMC4xOCwyLjQ0My0wLjc2MSw0LjY2MS0xLjcyOCw2LjU5MSAgYy0xLjc4NCwzLjU2Ny00LjcwMSw1Ljg0LTguNjY5LDYuNzU1Yy02LjUxLDEuNTAxLTE0Ljc4Ni0wLjk2OC0yMi4xNDUtNi42MDRjLTkuMjc0LTcuMTAxLTE1LjUyNi0xOC4xNzItMTYuMjAzLTI3LjkwOCAgbDMyLjY3OCwxNC40MDdjMC41MjQsMC4yMzEsMS4wNzIsMC4zNDEsMS42MTEsMC4zNDFjMS41MzYsMCwzLjAwMi0wLjg5MSwzLjY2Mi0yLjM4N2MwLjg5Mi0yLjAyMS0wLjAyNS00LjM4My0yLjA0Ny01LjI3NCAgbC0zNi4wNzItMTUuOTA0Yy0wLjEyNy0wLjMxNy0wLjI3Ni0wLjYyNS0wLjQ1MS0wLjkxN2wtNS42NzktMzYuNjQ5YzAtMi4yMDktMS43OTEtNC00LTRzLTQsMS43OTEtNCw0bDUuNjc5LDMyLjI4MyAgYy05LjExMy00LjEzOC0xNy4zMDQtMTQuNTYyLTIwLjMxOC0yNi40Yy0yLjI4Ny04Ljk4MS0xLjMwMS0xNy41NjMsMi42NC0yMi45NTZjMi40MDItMy4yOSw1LjY0MS01LjA3OCw5LjYyMy01LjMxNiAgYzIuMTUyLTAuMTI3LDQuNDE4LDAuMjEsNi43MzksMS4wMDZjMS42MTgsMC41NTQsMy4zOSwwLjQ1LDQuOTMxLTAuMjk0YzIuMjE0LTEuMDY3LDQuNDI3LTEuNjczLDYuNTc1LTEuODAyICBjMy41NTItMC4yMSw2LjcwNiwwLjg5Miw5LjMyOSwzLjI4NGMzLjMzNSwzLjA0Miw1LjU1Miw3Ljk2NCw2LjI4NCwxMy43ODZjLTAuMTU0LDAuMTI4LTAuMzExLDAuMjQ3LTAuNDY1LDAuMzc2ICBjLTE0Ljg4NSwxMi40NzMtMTcuNjAxLDMzLjI4My05Ljc5OSwzNy45NDZjMS45MjcsMS4xNTIsNC41MTgsMC45NTIsNi4zLTAuNDE0YzguNDgyLTYuNSwzNy41NjYsMy42NzYsNDIuMTg0LDE1LjMgIEMzMTUuNzkzLDM2Ni41MDUsMzE1Ljg2NiwzNzAuMTQzLDMxNC4wNzksMzczLjcxNHogTTMxNi40NTcsMzIwLjAzNGMtMS4wMjUsNC4wMDItNi42ODEsMTAuNjk4LTE2Ljc3NSwxNi41MzUgIGMtMC4xMjgtMC4wMzctMS42NTUtMC40NzMtMS42NTUtMC40NzNjLTIuNTYtMC43MzUtNS44NDUtMS42NjYtOS40OTEtMi4zNTFsNi4zMjYtNC4xN2MxLjg0NS0xLjIxNiwyLjM1NC0zLjY5NywxLjEzOS01LjU0MiAgYy0xLjIxNy0xLjg0NC0zLjY5Ny0yLjM1My01LjU0MS0xLjEzOGwtMTUuNDcxLDEwLjE5OGMtMC4zMDIsMC4wMzgtMC42MDMsMC4wNzQtMC45MDUsMC4xMjFjMS4wMzItMy45NTUsMy43MjQtOC4yMTEsOS44NzEtMTMuMzYzICBjMTAuNDcyLTguNzc1LDIwLjIyNC0xMi4wNTEsMjQuODI5LTExLjM3MmMwLjU5OCwwLjA4NywxLjM2NywwLjI4LDEuNjQ3LDAuNzA1YzAuMjM5LDAuMzYzLDAuMzY3LDEuMDUyLDAuMzUxLDEuODkxICBjLTAuMDUzLDIuNjk1LDEuNTYzLDUuMTQ0LDQuMDYyLDYuMTU1YzAuNzc3LDAuMzE0LDEuMzYyLDAuNzA0LDEuNjAyLDEuMDY3QzMxNi43MTcsMzE4LjcwOSwzMTYuNjAxLDMxOS40NjksMzE2LjQ1NywzMjAuMDM0eiAgIE00MDYuMDMxLDIxMS4zMzRsLTY1LjAwNSw4Mi40NjVjLTEuMjgyLDEuNjI3LTMuMTg3LDIuNDc3LTUuMTA4LDIuNDc3Yy0xLjQwOSwwLTIuODI4LTAuNDU2LTQuMDItMS4zOTYgIGMtMi44MTktMi4yMjItMy4zMDQtNi4zMDktMS4wODEtOS4xMjhsNjUuMDA1LTgyLjQ2NWMyLjIyMi0yLjgyLDYuMzEyLTMuMzAzLDkuMTI4LTEuMDgxICBDNDA3Ljc2OSwyMDQuNDI4LDQwOC4yNTQsMjA4LjUxNCw0MDYuMDMxLDIxMS4zMzR6IE0yNzUuMDczLDI2OC4xNzJjMC0wLjYxNCwwLjA4OC0xLjIzOCwwLjI3MS0xLjg1Nmw2MS4zNjYtMjA2LjQ5MiAgYzEuMDIzLTMuNDQxLDQuNTk1LTUuMjMzLDguMDgyLTQuMzc5YzUuMTY5LDEuMjY2LDQuNTYzLDcuNDY0LDQuMzc5LDguMDgybC0zMC42ODYsMjE0LjgzOWMtMC40NTIsMy4yNTItMy4yMzcsNS42MDctNi40MzEsNS42MDcgIGMtMC4yOTgsMC0wLjU5OS0wLjAyMS0wLjkwMS0wLjA2M2MtMy41NTctMC40OTQtNi4wMzgtMy43NzYtNS41NDUtNy4zMzJsMTcuMzIzLTEyNC43NTZsLTM1LjEyNiwxMTguMTk3ICBjLTAuODM5LDIuODIzLTMuNDI2LDQuNjUtNi4yMjgsNC42NWMtMC42MTMsMC0xLjIzNi0wLjA4Ny0xLjg1NC0wLjI3MUMyNzYuOSwyNzMuNTU5LDI3NS4wNzQsMjcwLjk3NCwyNzUuMDczLDI2OC4xNzJ6IiBmaWxsPSIjRkZGRkZGIi8+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+CjxnPgo8L2c+Cjwvc3ZnPgo=)}';
        $style = $style . 'div.ambrosia {margin:8px;display: inline-block;width: 32px;height: 32px;background-image: url(data:image/svg+xml;utf8;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/Pgo8IS0tIEdlbmVyYXRvcjogQWRvYmUgSWxsdXN0cmF0b3IgMTkuMC4wLCBTVkcgRXhwb3J0IFBsdWctSW4gLiBTVkcgVmVyc2lvbjogNi4wMCBCdWlsZCAwKSAgLS0+CjxzdmcgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgdmVyc2lvbj0iMS4xIiBpZD0iQ2FwYV8xIiB4PSIwcHgiIHk9IjBweCIgdmlld0JveD0iMCAwIDUxMi4wMDEgNTEyLjAwMSIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAwIDAgNTEyLjAwMSA1MTIuMDAxOyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSIgd2lkdGg9IjMycHgiIGhlaWdodD0iMzJweCI+CjxnPgoJPGc+CgkJPHBhdGggZD0iTTQ5NS40NTUsMC4wOTRsLTI3LjE4NywyLjgxM2MtMzMuOTc0LDMuNTE1LTY0LjgwOSwyNC4wMTQtODMuOTY3LDUxLjk0NGMtNS40ODgtMTIuMjk2LTEzLjk3LTI1LjI5OC0yNi44NDctMzguMTc0ICAgIGMtNS44NTQtNS44NTQtMTUuMzU1LTUuODU3LTIxLjIxMiwwbC0yMS4yMTEsMjEuMjEyYy0zOS4yNTQsMzkuMjU0LTQwLjk2MSwxMDEuNzQyLTUuMTE4LDE0Mi45OTFsLTIyLjQ4OSwyMi40ODggICAgYzAuMDA5LTIwLjc5MS02LjQ0OC01MS4wNDktMzYuMDMtODAuNjMxYy01Ljg1OC01Ljg1OC0xNS4zNTQtNS44NTgtMjEuMjEyLDBsLTIxLjIxMiwyMS4yMTIgICAgYy0zOS4xNTIsMzkuMTUyLTQwLjg1NSwxMDEuNzg0LTUuMTE3LDE0Mi45ODRsLTIyLjQzMSwyMi40M2MwLjAyMy0yNi43MDktMTAuNjM3LTU1LjExNS0zNi4wODgtODAuNTY2ICAgIGMtNS44NTctNS44NTgtMTUuMzUzLTUuODU4LTIxLjIxMiwwbC0yMS4yMTIsMjEuMjEyYy0zOS4xNTEsMzkuMTUxLTQwLjg1NSwxMDEuNzgtNS4xMjEsMTQyLjk4MUw0LjM5Myw0ODYuMzgyICAgIGMtNS44NTgsNS44NTgtNS44NTgsMTUuMzU0LDAsMjEuMjEyYzUuODU4LDUuODU5LDE1LjM1NSw1Ljg1OCwyMS4yMTIsMC4wMDFsOTMuMzk4LTkzLjM5MSAgICBjNDEuMjM4LDM1Ljc4NSwxMDMuODcxLDM0LjAyNiwxNDIuOTk5LTUuMTAzbDIxLjIxMi0yMS4yMTJjNS44NTgtNS44NTgsNS44NTgtMTUuMzU0LDAtMjEuMjEyICAgIGMtMjkuNTkxLTI5LjU5MS01OS44NDctMzYuMDQyLTgwLjY1LTM2LjAyOWwyMi40OTktMjIuNDk3YzQxLjE4NSwzNS43OTQsMTAzLjY4NSwzNC4yMDUsMTQyLjk5OS01LjExbDIxLjIxMi0yMS4yMTIgICAgYzUuODU4LTUuODU4LDUuODU4LTE1LjM1NCwwLTIxLjIxMmMtMjUuNDU3LTI1LjQ1Ny01My44Ny0zNi4xMTYtODAuNTg0LTM2LjA4OGwyMi40MzctMjIuNDM1ICAgIGM0MS4xODMsMzUuNzg5LDEwMy42ODEsMzQuMjAxLDE0Mi45OTUtNS4xMTRsMjEuMjEyLTIxLjIxMmM1Ljg1OC01Ljg1OCw1Ljg1OC0xNS4zNTQsMC0yMS4yMTIgICAgYy0xMi4yMDUtMTIuMjA2LTI1LjA5Mi0yMS4wMS0zOC4xNDQtMjYuODY3YzI4LjA0OC0xOS4yNSw0OC40MTctNTAuMTI3LDUxLjkxNS04My45NDdsMi44MTMtMjcuMTg3ICAgIEM1MTIuODk3LDcuMDkxLDUwNC45MTEtMC44ODQsNDk1LjQ1NSwwLjA5NHogTTExOS4wNzYsMzcxLjcwN2MtMjQuMDgyLTI5LjQyOC0yMi40MDEtNzMuMDM2LDUuMDQ3LTEwMC40ODRsOS45MTgtOS45MTkgICAgYzI4LjEzNSwzNi44ODgsMTguNjYxLDc2LjQyMy00LjIxMSw5OS42NDlMMTE5LjA3NiwzNzEuNzA3eiBNMjUwLjcsMzc3Ljk3OWwtOS45MSw5LjkxMSAgICBjLTI3LjQ1MywyNy40NTItNzEuMDY1LDI5LjEyOS0xMDAuNDk1LDUuMDRjMTAuNzM5LTEwLjczOSwyOC4yMzUtMzEuNjMsNjAuOTYzLTMyLjI3ICAgIEMyMTguNDYyLDM2MC4zMjQsMjM1LjMxMSwzNjYuMjcsMjUwLjcsMzc3Ljk3OXogTTIyNS4xMzksMjY1LjY1MWMtMjQuMDg0LTI5LjQyOS0yMi40MDQtNzMuMDQsNS4wNDUtMTAwLjQ4OWw5LjkxLTkuOTEgICAgYzI2LjU5MSwzNC45NDYsMjAuNTA5LDc0Ljc1LTQuNDE2LDk5Ljg2MUwyMjUuMTM5LDI2NS42NTF6IE0zNTYuNzY5LDI3MS45MTFsLTkuOTE3LDkuOTE3ICAgIGMtMjcuOTYsMjcuOTU5LTcxLjU2NCwyOC43ODEtMTAwLjQ5OCw1LjA0M2wxMC4zNDUtMTAuMzQ1QzI3OS44NjgsMjUzLjM1OCwzMTkuNjYxLDI0My42MDcsMzU2Ljc2OSwyNzEuOTExeiBNMzMxLjIwMSwxNTkuNTk4ICAgIGMtMjMuNzUxLTI4Ljk0OS0yMi45MDItNzIuNTUxLDUuMDQ0LTEwMC40OTZsOS45MDktOS45MWMyNi42NSwzNS4wMjQsMjAuNDc3LDc0Ljk0NS00LjU3NiwxMDAuMDI5TDMzMS4yMDEsMTU5LjU5OHogICAgIE00NjIuODI5LDE2NS44NWwtOS45MTcsOS45MTdjLTI3Ljk1OCwyNy45NTgtNzEuNTYyLDI4Ljc4Mi0xMDAuNDk4LDUuMDQzbDEwLjM0NS0xMC4zNDUgICAgQzM4NS45MjgsMTQ3LjI5Nyw0MjUuNzIxLDEzNy41NDcsNDYyLjgyOSwxNjUuODV6IE00NzkuMjY3LDQwLjY1N2MtMy45NjEsMzguMjg3LTM4LjMzMSw3Mi42NTgtNzYuNjE5LDc2LjYxOWwtOC44MjMsMC45MTMgICAgbDAuOTEzLTguODI0YzMuOTYxLTM4LjI4NywzOC4zMzEtNzIuNjU4LDc2LjYxOS03Ni42MTlsOC44MjQtMC45MTNMNDc5LjI2Nyw0MC42NTd6IiBmaWxsPSIjRkZGRkZGIi8+Cgk8L2c+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPC9zdmc+Cg==)}';
        // TABLE
        $css = $this->ReadPropertyString('table');
        $style = $style . 'table.pci ' . $css;
        $css = $this->ReadPropertyString('thead');
        $style = $style . '.pci thead ' . $css;
        $gra = $this->ReadPropertyBoolean('thgrad');
        if ($gra) {
            $style = $style . '.pci thead {background-image: linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-image: -o-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-image: -moz-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-image: -webkit-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-image: -ms-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%);}';
        }
        $css = $this->ReadPropertyString('thtd');
        $style = $style . '.pci th, td ' . $css;
        $css = $this->ReadPropertyString('trl');
        $style = $style . '.pci tr:last-child ' . $css;
        $css = $this->ReadPropertyString('tre');
        $style = $style . '.pci tr:nth-child(even) ' . $css;
        $css = $this->ReadPropertyString('tdf');
        $style = $style . '.pci td:first-child ' . $css;
        $css = $this->ReadPropertyString('tdm');
        $style = $style . '.pci td:not(:first-child):not(:last-child) ' . $css;
        $css = $this->ReadPropertyString('tdl');
        $style = $style . '.pci td:last-child ' . $css;
        // DATE
        $css = $this->ReadPropertyString('day');
        $style = $style . '.day ' . $css;
        $css = $this->ReadPropertyString('num');
        $style = $style . '.num ' . $css;
        $css = $this->ReadPropertyString('mon');
        $style = $style . '.mon ' . $css;
        // SCALE
        $style = $style . '.scale {clear:both;display: inline-block;width: 8px;height: 8px;margin-left: 2px; border: solid 1px rgba(255, 255, 255, 0.2)}';
        $style = $style . '.square1 { background-color: darkgreen;}';
        $style = $style . '.square2 { background-color: green;}';
        $style = $style . '.square3 { background-color: greenyellow;}';
        $style = $style . '.square4 { background-color: gold;}';
        $style = $style . '.square5 { background-color: tomato;}';
        $style = $style . '.square6 { background-color: red;}';
        $style = $style . '.square7 { background-color: crimson;}';
        $style = $style . '</style>';
        // Tabelle bauen
        $html = $style;
        $html = $html . '<table class=\'pci\'>';
        // Header
        $html = $html . '<thead><tr>';
        $html = $html . '<th>Tag</tf>';
        $size = count($pollination);
        $index = 0;
        foreach ($pollination as $key => $value) {
            $html = $html . "<th>$key</th>";
            $index++;
        }
        $html = $html . '</tr></thead>';
        // Datenzeilen
        $days = $this->ReadPropertyInteger('Days');
        if ($this->ReadPropertyBoolean('CreateSelect')) {
            $days = $this->GetValue('Days');
        }
        $st = $time;
        for ($i = 0; $i < $days; $i++) {
            // zeit
            $wd = $day[date('w', $st)];
            $md = date('j', $st);
            $mo = date('F', $st);
            $mo = strtr($mo, $trans);
            $item_day = '<div class=\'day\'>' . $wd . '</div><div class=\'num\'>' . $md . '</div><div class=\'mon\'>' . $mo . '</div>';
            // pollen
            $html = $html . '<tr><td>' . $item_day . '</td>';
            $index = 0;
            foreach ($pollination as $key => $value) {
                if ($index == $size - 1) {
                    $html = $html . "<td>$key</td>";
                } else {
                    $html = $html . '<td>' . $this->FormatScale($key, $value[$i]) . '</td>';
                }
            }
            $html = $html . '</tr>';
            // Plus ein Tag
            $st = $st + 86400;
        }

        $html = $html . '</table>';
        // HTML ausgeben
        $this->SetValueString('Forecast', $html);
    }

    /**
     * This function creats the textual summary of the forecast.
     *
     * @param array $pollination Aarray of pollen count dates.
     */
    private function BuildText(array $pollination)
    {
        // Vorhersage für geringe Belastung
        $gb_text = '';
        // Vorhersage für mittlere Belastung
        $mb_text = '';
        // Vorhersage für hohe Belastung
        $hb_text = '';
        // Belastung durch ???
        foreach ($pollination as $key => $value) {
            switch ($value[0]) {
                case '2':
                case '3':
                    $gb_text = $gb_text . $key . ' ';
                    break;
                case '4':
                case '5':
                    $mb_text = $mb_text . $key . ' ';
                    break;
                case '6':
                case '7':
                    $hb_text = $hb_text . $key . ' ';
                    break;
            }
        }
        // Ansagetext zusammen stellen
        if ($gb_text !== '') {
            $gb_text = 'Geringe Belastung durch ' . str_replace(' ', ', ', trim($gb_text) . '.');
        }
        if ($mb_text !== '') {
            $mb_text = 'Mittlere Belastung durch ' . str_replace(' ', ', ', trim($mb_text) . '.');
        }
        if ($hb_text !== '') {
            $hb_text = 'Hohe Belastung durch ' . str_replace(' ', ', ', trim($hb_text) . '.');
        }
        $text = '';
        if ($gb_text !== '') {
            $text = trim($gb_text) . ' ';
        }
        if ($mb_text !== '') {
            $text = $text . trim($mb_text) . ' ';
        }
        if ($hb_text !== '') {
            $text = $text . trim($hb_text) . ' ';
        }
        // Nix los
        if ($text == '') {
            $text = 'Keine Belastung.';
        }
        // Text ausgeben
        $this->SetValueString('Hint', $text);
    }

    /**
     * This function translate a numeric scale values into text.
     *
     * @param string $type  Name of the plant.
     * @param int $level Level index of pollen count.
     */
    private function FormatScale(string $type, int $level)
    {
        // Style class
        $class = strtolower($type);
        $div = '<div style=\'clear:both\'>' . self::SCALE['#' . $level] . '</div><div class=\'' . $class . '\'></div><div>';
        for ($i = 1; $i <= 7; $i++) {
            if ($i <= $level) {
                $div = $div . '<span class=\'scale square' . $i . '\'></span>';
            } else {
                $div = $div . '<span class="scale"></span>';
            }
        }
        $div = $div . '</div>';
        // return html fragment
        return $div;
    }
}
