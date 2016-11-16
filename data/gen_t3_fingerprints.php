#!/usr/bin/php
<?php
chdir(dirname(__FILE__));

//Configuration
$input = 't3_fingerprints.ini';
$output = 't3_fingerprints.php';

$repo_host = 'http://sourceforge.net';
$repo_index = '/projects/typo3/files/TYPO3%20Source%20and%20Dummy/';

$printsByVersions = parse_ini_file($input, true);
$files = array();
foreach($printsByVersions as $ver=>$prints) {
    foreach($prints as $p){
        if(!isset($files[$p])){
            $files[$p] = array();
        }
        $files[$p][] = $ver;
    }
}

//Init
$index = file_get_contents($repo_host.$repo_index) or die('No network...');
file_put_contents($output, '')!==false or die('Can\'t write output...');
echo "\n".$repo_host.$repo_index."\n\n";

$matches = array();
preg_match_all('#href="'.$repo_index.'([^"]+)/"\s*title="Click to enter#', $index, $matches) or die('Can\'t parse repository page.');

foreach($matches[1] as $release){
    list (, $ver) = explode(' ', urldecode($release));
    echo $ver.' : ';
    if (!empty($printsByVersions[$ver])) {
        continue;
    }

    $downloadUrl = $repo_host.$repo_index.$release.'/typo3_src-'.$ver.'.tar.gz/download';
    $downloadOut = NULL;
    $tmpPath = '/tmp/t3src/typo3_src-'.$ver;
    if (!is_dir($tmpPath)) {
        $downloadOut = $tmpPath.'.tar.gz';
        exec('wget -O "'.$downloadOut.'"  "'.$downloadUrl.'"');

        if(!file_exists($downloadOut)){
            trigger_error('Can\'t download "'.$downloadUrl.'"');
        }
        exec('cd '.dirname($tmpPath).' && tar -xzf "'.$downloadOut.'"');
    }
    $prints = staticFilesFingerprints($tmpPath);
    if(!empty($prints)){
        echo count($prints) . " files\n";
        $ini = '['.$ver.']'."\n";
        foreach($prints as $p){
            $ini .= $p[1].' = "'.str_replace($tmpPath, '', $p[0]).'"'."\n";
        }
        $ini .= "\n";
        file_put_contents($input, $ini, FILE_APPEND);
    } else {
        trigger_error('No fingerprints found for "'.$ver.'"');
        file_put_contents('no-prints.log', $ver."\n", FILE_APPEND);
    }
    if(!is_null($downloadOut) && file_exists($downloadOut)){
        unlink($downloadOut);
    }
}

//Index files > fingerprints > versions
$printsByVersions = parse_ini_file($input, true);
$files = array();
foreach($printsByVersions as $ver=>$prints) {
    foreach($prints as $p){
        if(!isset($files[$p])){
            $files[$p] = array();
        }
        $files[$p][] = $ver;
    }
}

file_put_contents($output, '<?php'."\n".'$prints = unserialize(base64_decode("'.base64_encode(serialize($files)).'"));');

function staticFilesFingerprints($scanDir, $maxDepth = 10){
    $ret = array();
    foreach(glob($scanDir . '/*') as $file) {
        if(is_dir($file)){
            if($maxDepth > 0){
                $ret = array_merge($ret, staticFilesFingerprints($file, $maxDepth--));
            }
        } else {
            //avoid hidden files
            if(strpos(basename($file), '.') === 0){
                continue;
            }

            $fileType = strtolower(substr(strrchr($file,'.'),1));
            if(empty($fileType) || in_array($fileType, array('txt', 'css', 'js', 'html', 'htm', 'sql'))) {
                $ret[] = array($file, md5_file($file));
            }
        }
    }
    return $ret;
}
