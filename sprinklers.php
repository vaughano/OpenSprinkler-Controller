<?
#Start session
if(!isset($_SESSION)) session_start();

if(!defined('Sprinklers')) {

    #Tell main we are calling it
    define('Sprinklers', TRUE);

    #Required files
    require_once "main.php";
}
#Get data needed to render home page
$data = start_data();

#Redirect if not authenticated or grabbing page directly
if (!is_auth() || !isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') {header('Location: '.$base_url); exit();}
?>
<script><?php include_once("js/main.js.php"); ?></script>

<div data-role="page" id="sprinklers">
    <div data-theme="a" data-role="header" data-position="fixed" data-tap-toggle="false">
        <a data-icon="bars" data-iconpos="notext" href="#sprinklers-settings"></a>
        <a data-icon="gear" data-iconpos="notext" href="#settings">Settings</a>
        <h3 style="margin:0"><img height="40px" width="159px" src="img/logo.png" /></h3>
    </div>
    <div data-role="content" style="padding-top:0px">
        <div id="footer-running">
        </div>
        <div id="showupdate" class="red">
            <p style="margin:0;text-align:center">Update Available</p>
        </div>
        <ul data-role="listview" data-inset="true" id="weather-list">
            <li data-role="list-divider">Weather</li>
            <li><div id="weather"></div></li>
        </ul>
        <ul data-role="listview" data-inset="true" id="info-list">
            <li data-role="list-divider">Information</li>
            <li><a href="#status" data-onclick="get_status();">Current Status</a></li>
            <li><a href="#preview">Preview Programs</a></li>
            <li><a href="#logs">View Logs</a></li>
        </ul>
        <ul data-role="listview" data-inset="true" id="program-control-list">
            <li data-role="list-divider">Program Control</li>
            <li><a href="#programs" data-onclick="get_programs();">Edit Programs</a></li>
            <li><a href="#manual" data-onclick="get_manual();">Manual Control</a></li>
            <li><a href="#raindelay">Rain Delay</a></li>
            <li><a href="#runonce" data-onclick="get_runonce();">Run-Once Program</a></li>
            <li><a href="#" data-onclick="rsn();">Stop All Stations</a></li>
        </ul>
    </div>
    <div data-role="panel" id="sprinklers-settings" data-position-fixed="true" data-theme="a">
        <ul data-role="listview" data-theme="a">
            <li>Logged in as: <?php echo $_SESSION["username"] ?></li>
            <li>
                <div class="ui-grid-a">
                    <div class="ui-block-a"><br>
                        <label for="autologin">Auto Login</label>
                    </div>
                    <div class="ui-block-b">
                        <select name="autologin" id="s-autologin" data-role="slider">
                            <option value="off">Off</option>
                            <option value="on">On</option>
                        </select>
                    </div>
                </div>
            </li>
            <li>
                <div class="ui-grid-a">
                    <div class="ui-block-a"><br>
                        <label for="theme-select">Theme</label>
                    </div>
                    <div class="ui-block-b">
                        <select name="theme-select" id="s-theme-select" data-role="slider">
                            <option value="legacy">Legacy</option>
                            <option value="flat">Flat</option>
                        </select>
                    </div>
                </div>
            </li>
            <li data-icon="forward"><a href="#" data-onclick="export_config();">Export Configuration</a></li>
            <li data-icon="back"><a href="#" data-onclick="import_config();">Import Configuration</a></li>
            <li data-icon="delete"><a href="#" data-onclick="logout();">Logout</a></li>
            <li data-icon="info"><a href="#about">About</a></li>
        </ul>
    </div>
</div>

<div data-role="page" id="status">
    <div data-theme="a" data-role="header" data-position="fixed" data-tap-toggle="false">
        <h3>Current Status</h3>
        <a href="#sprinklers" data-onclick="gohome();" data-icon="back">Back</a>
        <a href="#" data-onclick="get_status();" data-icon="refresh">Refresh</a>
    </div>
    <div data-role="content">
        <p id="status_header"></p>
        <ul data-role="listview" data-inset="true" id="status_list">
        </ul>
        <p id="status_footer"></p>
    </div>
</div>

<div data-role="page" id="manual">
    <div data-theme="a" data-role="header" data-position="fixed" data-tap-toggle="false">
        <h3>Manual Control</h3>
        <a href="#" data-onclick="gohome();" data-icon="back">Back</a>
    </div>
    <div data-role="content">
        <p style="text-align:center">With manual mode turned on, tap a station to toggle it.</p>
        <ul data-role="listview" data-inset="true">
            <li data-role="fieldcontain">
                <label for="mmm"><b>Manual Mode</b></label>
                <select name="mmm" id="mmm" data-role="slider">
                    <option value="off">Off</option>
                    <option <?php echo $data["mm"]; ?> value="on">On</option>
                </select>
            </li>
        </ul>
        <ul data-role="listview" data-inset="true" id="mm_list">
        </ul>
    </div>
</div>

<div data-role="page" id="runonce">
    <div data-theme="a" data-role="header" data-position="fixed" data-tap-toggle="false">
        <h3>Run-Once Program</h3>
        <a href="#sprinklers" data-onclick="gohome();" data-icon="back">Back</a>
        <a href="#" data-onclick="submit_runonce();">Submit</a>
    </div>
    <div data-role="content" id="runonce_list">
    </div>
</div>

<div data-role="page" id="programs">
    <div data-theme="a" data-role="header" data-position="fixed" data-tap-toggle="false">
        <h3>Programs</h3>
        <a href="#sprinklers" data-onclick="gohome();" data-icon="back">Back</a>
        <a href="#addprogram" data-onclick="add_program();" data-icon="plus">Add</a>
    </div>
    <div data-role="content" id="programs_list">
    </div>
</div>

<div data-role="page" id="addprogram">
    <div data-theme="a" data-role="header" data-position="fixed" data-tap-toggle="false">
        <h3>Add Program</h3>
        <a href="#programs" data-icon="back">Back</a>
        <a href="#" data-onclick="submit_program('new');">Submit</a>
    </div>
    <div data-role="content" id="newprogram">
    </div>
</div>

<div data-role="page" id="raindelay">
    <div data-theme="a" data-role="header" data-position="fixed" data-tap-toggle="false">
        <h1>Rain Delay</h1>
        <a href="#sprinklers" data-onclick="gohome();" data-icon="back">Back</a>
    </div>
    <div data-role="content">
        <p style="text-align:center">Rain delay allows you to disable all programs for a set duration. You can manually set a rain delay or enable automatic rain delays.</p>
        <ul data-role="listview" data-inset="true">
            <li data-role="list-divider">Manual Rain Delay</li>
            <li>
                <p class="rain-desc">Enable manual rain delay by entering a value into the input below. To turn off a currently enabled rain delay use a value of 0.</p>
                <form action="javascript:raindelay()">
                    <div data-role="fieldcontain">
                        <label for="delay">Duration (in hours):</label>
                        <input type='number' pattern='[0-9]*' data-highlight='true' data-type='range' value='0' min='0' max='96' id='delay' />
                    </div>
                    <input type="submit" value="Submit" data-theme="a" />
                </form>
            </li>
        </ul>
        <ul data-role='listview' data-inset='true'>
            <li data-role='list-divider'>Automatic Rain Delay</li>
            <li>
                <p class="rain-desc">When automatic rain delay is enabled, the weather will be checked for rain every hour. If the weather reports any condition suggesting rain, a rain delay is automatically issued using the below set delay duration.</p>
                <form action="javascript:auto_raindelay()">
                    <div data-role='fieldcontain'>
                        <label for='auto_delay'>Auto Rain Delay</label>
                        <select name='auto_delay' id='auto_delay' data-role='slider'>
                            <option value='off'>Off</option>
                            <option value='on'>On</option>
                        </select>
                    </div>
                    <label for='auto_delay_duration'>Delay Duration (hours)</label>
                    <input type='number' pattern='[0-9]*' data-highlight='true' data-type='range' min='0' max='96' id='auto_delay_duration' />
                    <input type="submit" value="Submit" data-theme="a" />
                </form>
            </li>
        </ul>
    </div>
</div>

<div data-role="page" id="logs">
    <div data-theme="a" data-role="header" data-position="fixed" data-tap-toggle="false">
        <h3>Logs</h3>
        <a href="#sprinklers" data-onclick="gohome();" data-icon="back">Back</a>
        <a href="#" data-onclick="get_logs();" data-icon="refresh">Refresh</a>
    </div>
    <div data-role="content">
        <fieldset data-role="controlgroup" data-type="horizontal" data-mini="true" class="log_type">
            <input data-mini="true" type="radio" name="log_type" id="log_graph" value="graph" checked="checked" />
            <label for="log_graph">Graph</label>
            <input data-mini="true" type="radio" name="log_type" id="log_table" value="table" />
            <label for="log_table">Table</label>
            <input data-mini="true" type="radio" name="log_type" id="log_timeline" value="timeline" />
            <label for="log_timeline">Timeline</label>
        </fieldset>
        <div id="placeholder" style="display:none;width:100%;height:300px;"></div>
        <div id="zones">
        </div>
        <fieldset data-role="collapsible" data-mini="true" id="log_options">
            <legend>Options</legend>
            <fieldset data-role="controlgroup" data-type="horizontal" id="graph_sort" style="display:none;text-align:center">
              <p style="margin:0">Grouping:</p>
              <input data-mini="true" type="radio" name="g" id="radio-choice-d" value="n" checked="checked" />
              <label for="radio-choice-d">None</label>
              <input data-mini="true" type="radio" name="g" id="radio-choice-a" value="h" />
              <label for="radio-choice-a">Hour</label>
              <input data-mini="true" type="radio" name="g" id="radio-choice-b" value="d" />
              <label for="radio-choice-b">DOW</label>
              <input data-mini="true" type="radio" name="g" id="radio-choice-c" value="m" />
              <label for="radio-choice-c">Month</label>
            </fieldset>
            <div data-role="fieldcontain">
                <label for="log_start">Start:</label>
                <input data-mini="true" type="date" id="log_start" />
                <label for="log_end">End:</label>
                <input data-mini="true" type="date" id="log_end" />
            </div>
        </fieldset>
        <div id="logs_list">
        </div>
        <div id="log_timeline_panel" style="display:none">
        <div id="log_timeline_header">
            <a href="#" data-onclick="change_log_timeline_date(-1);"><img src="img/moveleft.png" /></a>
            <input style="text-align:center" type="date" name="log_timeline_date" id="log_timeline_date" />
            <a href="#" data-onclick="change_log_timeline_date(1);"><img src="img/moveright.png" /></a>
        </div>
        <div id="log_timeline_component"></div>
        <div id="log_timeline-navigation" style="display:none;width:144px;margin:0 auto">
            <div class="timeline-navigation-zoom-in" onclick="timeline.zoom(0.4)" title="Zoom in"></div>
            <div class="timeline-navigation-zoom-out" onclick="timeline.zoom(-0.4)" title="Zoom out"></div>
            <div class="timeline-navigation-move-left" onclick="timeline.move(-0.2)" title="Move left"></div>
            <div class="timeline-navigation-move-right" onclick="timeline.move(0.2)" title="Move right"></div>
        </div>
        </div>
        <div></div>
    </div>
</div>

<div data-role="page" id="settings">
    <div data-theme="a" data-role="header" data-position="fixed" data-tap-toggle="false">
        <h3>Settings</h3>
        <a href="#sprinklers" data-onclick="gohome();" data-icon="back">Back</a>
    </div>
    <div data-role="content">
        <ul data-role="listview" data-inset="true">
            <li><a href="#" data-onclick="clear_logs();">Clear Logs</a></li>
            <li><a href="#" data-onclick="show_settings();">Device Options</a></li>
            <li><a href="#" data-onclick="show_stations();">Edit Stations</a></li>
            <li><a href="#" data-onclick="show_users();">User Management</a></li>
        </ul>
        <ul data-role="listview" data-inset="true">
            <li data-role="list-divider">System Control</li>
            <li data-role="fieldcontain">
                <label for="mm"><b>Manual Mode</b></label>
                <select name="mm" id="mm" data-role="slider">
                    <option value="off">Off</option>
                    <option <?php echo $data["mm"]; ?> value="on">On</option>
                </select>
            </li>
            <li data-role="fieldcontain">
                <label for="en"><b>Operation</b></label>
                <select name="en" id="en" data-role="slider">
                    <option value="off">Off</option>
                    <option <?php echo $data["en"]; ?> value="on">On</option>
                </select>
            </li>
            <li data-icon="alert"><a href="#" data-onclick="rbt();">Reboot OpenSprinkler</a></li>
        </ul>
        <ul data-role="listview" data-inset="true">
            <li data-role="list-divider">Automatically Disable Manual Mode</li>
            <li>
                <p class="rain-desc">Automatically disable manual mode at midnight. Use this option to turn off manual mode and ensure programs run even if you forget manual mode enabled.</p>
            </li>
            <li data-role="fieldcontain">
                <label for="auto_mm"><b>Enabled</b></label>
                <select name="auto_mm" id="auto_mm" data-role="slider">
                    <option value="off">Off</option>
                    <option <?php global $auto_mm; echo (($auto_mm) ? "selected" : "") ?> value="on">On</option>
                </select>
            </li>
        </ul>
    </div>
</div>

<div data-role="page" id="os-settings">
    <div data-theme="a" data-role="header" data-position="fixed" data-tap-toggle="false">
        <h3>OS Settings</h3>
        <a href="#settings" data-icon="back">Back</a>
        <a href="#" data-onclick="submit_settings();">Submit</a>
    </div>
    <div data-role="content">
        <ul data-role="listview" data-inset="true" id="os-settings-list">
        </ul>
    </div>
</div>

<div data-role="page" id="user-control">
    <div data-theme="a" data-role="header" data-position="fixed" data-tap-toggle="false">
        <h3>User Control</h3>
        <a href="#settings" data-icon="back">Back</a>
        <a href="#add-user" data-icon="plus">Add</a>
    </div>
    <div data-role="content" id="user-control-list">
    </div>
</div>

<div data-role="page" id="add-user">
    <div data-theme="a" data-role="header" data-position="fixed" data-tap-toggle="false">
        <h3>Add User</h3>
        <a href="#user-control" data-icon="back">Back</a>
        <a href="#" data-onclick="add_user();">Submit</a>
    </div>
    <div data-role="content">
        <ul data-inset="true" data-role="listview">
            <li data-role="list-divider">Add New User</li>
            <li>
                <div data-role="fieldcontain">
                    <label for="name">Username:</label>
                    <input autocapitalize="off" autocorrect="off" type="text" id="name" value="" />
                    <label for="pass">Password:</label>
                    <input type="password" id="pass" value="" />
                    <a data-role="button" href="#" data-onclick="add_user();" data-theme="a">Submit</a>
                </div>
            </li>
        </ul>
    </div>
</div>

<div data-role="page" id="os-stations">
    <div data-theme="a" data-role="header" data-position="fixed" data-tap-toggle="false">
        <h3>Edit Stations</h3>
        <a href="#settings" data-icon="back">Back</a>
        <a href="#" data-onclick="submit_stations();">Submit</a>
    </div>
    <div data-role="content">
        <ul data-role="listview" data-inset="true" id="os-stations-list">
        </ul>
    </div>
</div>

<div data-role="page" id="preview">
    <div data-theme="a" data-role="header" data-position="fixed" data-tap-toggle="false">
        <h3>Program Preview</h3>
        <a href="#sprinklers" data-onclick="gohome();" data-icon="back">Back</a>
    </div>
    <div data-role="content">
        <div id="preview_header">
            <a href="#" data-onclick="changeday(-1);"><img src="img/moveleft.png" /></a>
            <input style="text-align:center" type="date" name="preview_date" id="preview_date" />
            <a href="#" data-onclick="changeday(1);"><img src="img/moveright.png" /></a>
        </div>
        <div id="timeline"></div>
        <div id="timeline-navigation" style="display:none;width:144px;margin:0 auto">
            <div class="timeline-navigation-zoom-in" onclick="timeline.zoom(0.4)" title="Zoom in"></div>
            <div class="timeline-navigation-zoom-out" onclick="timeline.zoom(-0.4)" title="Zoom out"></div>
            <div class="timeline-navigation-move-left" onclick="timeline.move(-0.2)" title="Move left"></div>
            <div class="timeline-navigation-move-right" onclick="timeline.move(0.2)" title="Move right"></div>
        </div>
    </div>
</div>

<div data-role="page" id="about">
    <div data-theme="a" data-role="header" data-position="fixed" data-tap-toggle="false">
        <h3>About</h3>
        <a href="#sprinklers" data-onclick="gohome();" data-icon="back">Back</a>
    </div>
    <div data-role="content">
        <div data-role="collapsible-set">
            <div data-role="collapsible">
                <h3>Background</h3>
                <p>I, Samer Albahra, am a medical school graduate, currently doing a pathology residency at UTHSCSA. I enjoy making mobile applications in my spare time and was excited when I first discovered the OpenSprinkler, an open-source Internet based sprinkler system, which lacked a truly mobile interface.</p>
                <p>I decided to add a mobile front-end using jQuery Mobile. There were a few things I wanted to accomplish:</p>
                <ul><li>Large on/off buttons in manual mode</li><li>Easy slider inputs for any duration input</li><li>Compatibility between many/all devices</li><li>Easy feedback of current status</li><li>Easy program input/modification</li></ul>
                <p>Fortunately, I had a lot of feedback on Ray's forums and now have an application that has been tested across many devices and installed in many unique environments.</p>
                <p>I fully support every feature of the OpenSprinkler and also the OpenSprinkler Pi (using the interval program).</p>
                <p>Changelog can be viewed on <a target="_blank" href="https://github.com/salbahra/OpenSprinkler-Controller/commits/master">Github</a>.</p>
            </div>
            <div data-role="collapsible" data-collapsed="false">
                <h3>Donate</h3>
                    <p style="text-align:center;overflow: visible;white-space: normal;">This web app has been developed by Samer Albahra. If you enjoy it please donate by clicking the button below.</p>
                    <form style='text-align:center' action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
                        <input type="hidden" name="cmd" value="_s-xclick">
                        <input type="hidden" name="hosted_button_id" value="89M484QR2TCFJ">
                        <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
                        <img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
                    </form>
            </div>
        </div>
        <p id='versions'>
            <?php
                echo "Firmware Version: ".$data["ver"];
                if (file_exists(".git/FETCH_HEAD")) {
                    $data = file_get_contents(".git/FETCH_HEAD");
                    if ($data !== false) {
                        preg_match("/\w{40}/", $data, $commit);
                        echo "<br>Mobile Version: <span id='commit' data-commit='".$commit[0]."'><a target='_blank' href='https://github.com/salbahra/OpenSprinkler-Controller/commit/".$commit[0]."'>".substr($commit[0], 0,7)."</a></span>";
                    }
                }
            ?>
         </p>
    </div>
</div>

<div data-role="dialog" id="sure" data-title="Are you sure?">
    <div data-role="content">
        <h3 class="sure-1" style="text-align:center"></h3>
        <p class="sure-2" style="text-align:center"></p>
        <a class="sure-do" data-role="button" data-theme="b" href="#">Yes</a>
        <a class="sure-dont" data-role="button" data-theme="c" href="#">No</a>
    </div>
</div>