<?php

    require(__DIR__.'/../../../config.php');
    require_once(__DIR__.'/../lib.php');
    require_once(__DIR__.'/../library.php');

    defined('MOODLE_INTERNAL') || die();
    
    echo "  <table border='1'>

                <tr>
                    <th>Name</th>
                </tr>";

    echo "      <tr>
                    <td style='align:center;'>".($_GET['keyname'])."</td>
                </tr>";

    echo "</table>";
    $object = new database_calls;
    echo $object->getActivities($_GET['keyname'],$_GET['value']);
   
?>