<?php
if (PHP_SAPI === 'cli') {
  echo "INFO: Starting commandline utility -> newproject " . PHP_EOL;
  if ($argc > 3){
    echo "WARNING: This utility only accepts two parameters. Passing in more then this may produce unexpected results " . PHP_EOL;
  }
  // Import the build.config.php file
  if (!defined('MODX_CORE_PATH')) {
    /* You can place the absolute path of the build.config.php file here to gerentee
    that the commandline utilities will find it*/
    $path1 = '/home/bushost/public_html/test/assets/mycomponents/example/_build/build.config.php';
    if (file_exists($path1)) {
        include $path1;
    } else {
        $path2 = dirname(dirname(dirname(__FILE__))) . '/_build/build.config.php';
        if (file_exists($path2)) {
            include($path2);
        }
    }
    if (!defined('MODX_CORE_PATH')) {
        session_write_close();
        die('[newproject.php] Could not find build.config.php');
    }
  }

  ### VAriables ###
  $corePath = '/home/bushost/public_html/test/core/';
  $mcCorePath = $corePath . 'components/mycomponent/';
  $mcConfigPath = $mcCorePath . '_build/config/';
  if (!empty($argv[1])){
    $newProjectName = $argv[1];
    $currentProject = $newProjectName;
    $newProjectLower = strtolower($newProjectName);
  }
  else{
    echo "ERROR: No name parameter was passed" . PHP_EOL;
    return false;
  }
  if (!empty($argv[2])){
    $debugSetting = $argv[2];
    echo "debugSetting is now filled with -> " . $debugSetting  . PHP_EOL;
  }
  $cpFile = $corePath . 'components/mycomponent/_build/config/current.project.php';
  $output = '';
  $projects = '';
  $code = '';

  ### Functions ###
  function debug($message,$debugSetting) {
    if($debugSetting == 'debug'){
      echo "DEBUG: " . $message . PHP_EOL;
    }
  }

  ### Main Utility Code ###
  $content = file_get_contents($cpFile);
  ### Updating the current.progect.php file ###
  /* Delete old current.project.php file */
  unlink($cpFile);
  /* update MC current.project.php file */
  $previousProject = preg_replace('/.*\$currentProject = \'(.*)\';/s', '$1', $content);
  debug("Previous project variables -> " . $previousProject,$debugSetting);
  $currentProjectContent = "<?php
  /** MyComponent Current Project
   *  Change this file whenever you work on another project
   *
   *  This should be set to the lowercase name of your package and
   *  Should match the \$packageNameLower value in the Project Config
   *  file (which must be named {packageNameLower}.config.php)
   * */
   \$currentProject = '" . $newProjectName . "';";
  $fp = fopen($cpFile, 'w');
  fwrite($fp, $currentProjectContent);
  fclose($fp);
  $currentProject = $newProjectLower;

  ### Check if the consoleprojects.json file exists ###
  if (file_exists('consoleprojects.json')) {
    // if the json file exists read its contents into a variable and decode them
    echo 'The consoleprojects.json file exists' . PHP_EOL;
  } else {
    // If the json file does not exists then create it and fill it with the previos project name
    echo "The consoleprojects.json file does not exist"  . PHP_EOL;
    $jsonObject = array($previousProject => $previousProject);
    //file_put_contents('consoleprojects.json', json_encode($jsonObject));
    $fp = fopen('consoleprojects.json', 'w');
    fwrite($fp,  json_encode($jsonObject));
    fclose($fp);
  }
  $consoleProjectsJsonFile = json_decode(file_get_contents("consoleprojects.json"), true);

  ### Creating a new progect.config.php file ###
  if(array_key_exists($newProjectName, $consoleProjectsJsonFile)){
    echo "That Project name has already been taken. Please choose another or delete the old project";
    return false;
  }
  /* create new project config file */
  $newTpl = file_get_contents($mcConfigPath . 'example.config.php');
  if (empty($newTpl)) {
      $message = 'Could not find example.config.php';
      return false;
  }
  $newTpl = str_replace('Example', $newProjectName, $newTpl);
  $newTpl = str_replace('example', $newProjectLower, $newTpl);
  //debug("newTpl Variable -> " . $newTpl,$debugSetting);
  if (! is_dir($mcConfigPath)) {
      echo 'MyComponent Config directory does not exist';
      return false;
  }
  $configFile = $mcConfigPath . $newProjectLower . '.config.php';
  $fp = fopen($configFile, 'w');
  if ($fp) {
      fwrite($fp, $newTpl);
      fclose($fp);
  } else {
    echo 'Could not open new config file';
    return false;
  }
  echo "SUCCESS: new config file created. Its recommended that you edit the new config file before running any utilities". PHP_EOL;
  debug("Project Name -> " . $newProjectName,$debugSetting);
  debug("Debug Setting -> " . $debugSetting,$debugSetting);
  if (file_exists('consoleprojects.json')) {
    // if the json file exists read its contents into a variable and decode them
    debug('Adding ' . $newProjectName . 'to the consoleprojects.json file',$debugSetting);
    $entry = array($newProjectName => $newProjectName);
    $consoleProjectsJsonFile[] = $entry;
    //file_put_contents('consoleprojects.json', json_encode($jsonObject));
    $fp = fopen('consoleprojects.json', 'w');
    fwrite($fp,  json_encode($consoleProjectsJsonFile));
    fclose($fp);
  }
  echo "INFO: Stopping commandline utility -> newproject " . PHP_EOL;
}
else {
    echo "This utility can only be accessed through the commandline " . PHP_EOL;
}
?>
