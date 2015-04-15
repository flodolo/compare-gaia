<?php

function extractFileNames($tmx)
{
    // Keys are named like 'shared/date/date.properties:minutes-until-long[one]'
    $files = [];
    foreach ($tmx as $key => $value) {
        $file_name = [];
        if (preg_match('/^.*:/', $key, $file_name)) {
            $tmp_file_name = rtrim($file_name[0], ':');
            if (! in_array($tmp_file_name, $files)) {
                array_push($files, $tmp_file_name);
            }
        }
    }
    sort($files);

    return $files;
}

function compareSingleFile($file_name, $tmx_base, $tmx_new)
{
    $strings_base = [];
    $strings_new = [];
    $results = [
        'base'        => 0,
        'new'         => 0,
        'added'       => 0,
        'removed'     => 0,
        'common'      => 0,
        'common_perc' => 0,
    ];

    $regexp = '/^' . preg_quote($file_name, '/') . ':/';
    foreach ($tmx_base as $key => $value) {
        if (preg_match($regexp, $key)) {
            array_push($strings_base, $key);
        }
    }
    foreach ($tmx_new as $key => $value) {
        if (preg_match($regexp, $key)) {
            array_push($strings_new, $key);
        }
    }

    $results['base'] = count($strings_base);
    $results['new'] = count($strings_new);
    $results['added'] = count(array_diff($strings_new, $strings_base));
    $results['removed'] = count(array_diff($strings_base, $strings_new));
    $results['common'] = count(array_intersect($strings_base, $strings_new));

    $results['common_perc'] = $results['new'] ?
                              round($results['common'] / $results['new'] * 100, 2) :
                              0;

    return $results;
}

function compareVersions($base, $tmx_base, $new, $tmx_new)
{
    // Count strings
    $total_base = count($tmx_base);
    $total_new = count($tmx_new);
    $growth = round(($total_new - $total_base) / $total_base * 100, 2);
    $added_strings = count(array_diff_key($tmx_new, $tmx_base));
    $removed_strings = count(array_diff_key($tmx_base, $tmx_new));
    $common_strings = count(array_intersect_key($tmx_base, $tmx_new));
    $common_perc = round($common_strings / $total_new * 100, 2);

    /* Create a list of files. Make sure that we have all files, some can be
     * added or removed in one version
     */
    $base_files = extractFileNames($tmx_base);
    $new_files = extractFileNames($tmx_new);
    $file_list = array_unique(array_merge($base_files, $new_files));
    sort($file_list);

    $html_output = "<h2>Comparison between {$base} and {$new}</h2>\n" .
                   "<table class='table table-auto table-striped'>\n" .
                   "  <tbody>\n" .
                   "    <tr>\n" .
                   "      <th scope='row'>Number of strings in {$base}</th>\n" .
                   "      <td>{$total_base}</td>\n" .
                   "    </tr>\n" .
                   "    <tr>\n" .
                   "      <th scope='row'>Number of strings in {$new}</th>\n" .
                   "      <td>{$total_new}<br/><small>{$growth}%</small></td>\n" .
                   "    </tr>\n" .
                   "    <tr>\n" .
                   "      <th scope='row'>Added strings</th>\n" .
                   "      <td>{$added_strings}</td>\n" .
                   "    </tr>\n" .
                   "    <tr>\n" .
                   "      <th scope='row'>Removed strings</th>\n" .
                   "      <td>{$removed_strings}</td>\n" .
                   "    </tr>\n" .
                   "    <tr>\n" .
                   "      <th scope='row'>Common strings</th>\n" .
                   "      <td>{$common_strings}<br/><small>{$common_perc}% of {$new} strings</small></td>\n" .
                   "    </tr>\n" .
                   "  </tbody>\n" .
                   "</table>\n" .
                   "<h3>File details</h3>\n" .
                   "<p>Columns are sortable</p>\n" .
                   "<table class='table table-bordered table-condensed sortable'>\n" .
                   "  <thead>\n" .
                   "    <tr>\n" .
                   "      <th>File</th>\n" .
                   "      <th>Total {$base}</th>\n" .
                   "      <th>Total {$new}</th>\n" .
                   "      <th>Added</th>\n" .
                   "      <th>Removed</th>\n" .
                   "      <th>Common</th>\n" .
                   "      <th>%</th>\n" .
                   "    </tr>\n" .
                   "  </thead>\n" .
                   "  <tbody>\n";

    foreach ($file_list as $file_name) {
        $comparison = compareSingleFile($file_name, $tmx_base, $tmx_new);
        if ($comparison['common_perc'] > 90) {
            $row_class = 'class="success"';
        } elseif ($comparison['common_perc'] > 50) {
            $row_class = 'class="warning"';
        } else {
            $row_class = 'class="danger"';
        }
        $html_output .= "    <tr {$row_class}>\n" .
                        "      <td>{$file_name}</td>\n" .
                        "      <td class='text-right'>{$comparison['base']}</td>\n" .
                        "      <td class='text-right'>{$comparison['new']}</td>\n" .
                        "      <td class='text-right'>{$comparison['added']}</td>\n" .
                        "      <td class='text-right'>{$comparison['removed']}</td>\n" .
                        "      <td class='text-right'>{$comparison['common']}</td>\n" .
                        "      <td class='text-right'>{$comparison['common_perc']}%</td>\n" .
                        "    </tr>\n";
    }

    $html_output .= "  </tbody>\n" .
                    "</table>\n";

    return $html_output;
}

// Read form parameters
$base = isset($_GET['base']) ? $_GET['base'] : '2.1';
$new = isset($_GET['new']) ? $_GET['new'] : '2.2';
$html_output = '';

$versions = [
    '1.1',
    '1.2',
    '1.3',
    '1.4',
    '2.0',
    '2.1',
    '2.2',
    'master',
];

// Make sure we have valid data
$errors = '';
if (! in_array($base, $versions)) {
    $errors .= '<p>Requested version ' . $base . ' not available. Reset to default (2.1)</p>';
    $base = '2.1';
}
if (! in_array($new, $versions)) {
    $errors .= '<p>Requested version ' . $new  . ' not available. Reset to default (2.2)</p>';
    $new = '2.2';
}

// Create values for selects used in form
$select_options_base = '';
$select_options_new = '';
foreach ($versions as $version) {
    $selected = $version == $base ? 'selected=""' : '';
    $select_options_base .= "<option {$selected} value='{$version}'>{$version}</option>\n";
    $selected = $version == $new ? 'selected=""' : '';
    $select_options_new .= "<option {$selected} value='{$version}'>{$version}</option>\n";
}

$tmx_comparison = [];
// Import TMX base
include 'data/gaia_' . str_replace('.', '_', $base) . '.php';
$tmx_comparison['base'] = $tmx;
unset($tmx);
// Import TMX new
include 'data/gaia_' . str_replace('.', '_', $new) . '.php';
$tmx_comparison['new'] = $tmx;
unset($tmx);

$html_output .= compareVersions($base, $tmx_comparison['base'], $new, $tmx_comparison['new']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset=utf-8>
    <title>Gaia Comparison</title>
    <link rel="stylesheet" href="assets/bootstrap.min.css" type="text/css" media="all" />
    <link rel="stylesheet" href="assets/bootstrap-theme.min.css" type="text/css" media="all" />
    <link rel="stylesheet" href="assets/style.css" type="text/css" media="all" />
    <script src="assets/js/sorttable.js"></script>
</head>

<body>
    <div class="container">
        <h1>Gaia Comparison</h1>
        <?php
        if ($errors) {
            ?>
        <div class="panel panel-danger">
            <div class="panel-heading">
                <h3 class="panel-title">Version Error</h3>
            </div>
            <div class="panel-body">
                <?=$errors?>
            </div>
        </div>
        <?php

        }
        ?>
        <form class="form-horizontal" action="" method="get">
            <div class="form-group form-group-sm">
                <label class="col-sm-2" for="base-version">Reference version</label>
                <div class="col-sm-2">
                    <select class="form-control" name="base">
                        <?=$select_options_base?>
                    </select>
                </div>
            </div>
            <div class="form-group form-group-sm">
                <label class="col-sm-2" for="new-version">New version</label>
                <div class="col-sm-2">
                    <select class="form-control" name="new">
                        <?=$select_options_new?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-default">Compare versions</button>
        </form>
        <?=$html_output?>
    </div>
</body>
</html>
