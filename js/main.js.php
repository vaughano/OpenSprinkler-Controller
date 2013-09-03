<?php
#Start session
if(!isset($_SESSION)) session_start();

if(!defined('Sprinklers')) {

    #Tell main we are calling it
    define('Sprinklers', TRUE);

    #Required files
    require_once "../main.php";
    
    header("Content-type: application/x-javascript");
}

#Kick if not authenticated
if (!is_auth()) {header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found', true, 404);exit();}

#Echo token so browser can cache it for automatic logins
if (isset($_SESSION['sendtoken']) && $_SESSION['sendtoken']) { echo "localStorage.setItem('token', '".$_SESSION['token']."');\n"; $_SESSION['sendtoken'] = false; }
?>
//Set AJAX timeout
$.ajaxSetup({
    timeout: 6000
});

//Handle timeout
$(document).ajaxError(function(x,t,m) {
    if(t.status==401) {
        location.reload();
    }
    if(t.statusText==="timeout") {
        if (m.url.search("action=get_weather")) {
            $("#weather-list").animate({ 
                "margin-left": "-1000px"
            },1000,function(){
                $(this).hide();
            })
        } else {
            showerror("Connection timed-out. Please try again.")
        }
    }
});

//After main page is processed, hide loading message and change to the page
$(document).one("pageinit","#sprinklers", function(){
    $.mobile.hidePageLoadingMsg();
    var theme = localStorage.getItem("theme");
    $("#s-theme-select").val(theme).slider("refresh");
    var now = new Date();
    $("#log_start").val(new Date(now.getTime() - 604800000).toISOString().slice(0,10));
    $("#preview_date, #log_end, #log_timeline_date").val(now.toISOString().slice(0,10));
    $.mobile.changePage($("#sprinklers"),{transition:"none"});
    var curr = $("#commit").data("commit");
    if (curr !== null) {
        $.getJSON("https://api.github.com/repos/salbahra/OpenSprinkler-Controller/git/refs/heads/master").done(function(data){
            var newest = data.object.sha;
            if (newest != curr) $("#showupdate").slideDown().delay(2000).slideUp();
        })
    }
});

$(window).resize(function(){
    var currpage = $(".ui-page-active").attr("id");
    if (currpage == "logs") {
        showArrows();
        seriesChange();
    }
});

$("#logs input:radio[name='log_type'],#graph_sort input[name='g']").change(get_logs)

$("#log_start,#log_end").change(function(){
    clearTimeout(window.logtimeout);
    window.logtimeout = setTimeout(get_logs,500);
})

$("#placeholder").on("plothover", function(event, pos, item) {
    $("#tooltip").remove();
    clearTimeout(window.hovertimeout);
    if (item) window.hovertimeout = setTimeout(function(){showTooltip(item.pageX, item.pageY, item.series.label, item.series.color)}, 100);
});

$("#zones").scroll(showArrows)

$("#preview_date").change(function(){
    var id = $(".ui-page-active").attr("id");
    if (id == "preview") get_preview()
});

$("#log_timeline_date").change(function(){
    var id = $(".ui-page-active").attr("id");
    if (id == "logs") get_logs_timeline();
});

function get_logs_timeline() {
	var date = $("#log_timeline_date").val();
    if (date === "") return;
    date = date.split("-");

	var options = {
		'width':  '100%',
		'editable': false,
		'axisOnTop': true,
		'eventMargin': 10,
		'eventMarginAxis': 0,
		'min': new Date(date[0],date[1]-1,date[2],0),
		'max': new Date(date[0],date[1]-1,date[2],24),
		'selectable': true,
		'showMajorLabels': false,
		'zoomMax': 1000 * 60 * 60 * 24,
		'zoomMin': 1000 * 60 * 60,
		'groupsChangeable': false,
		'showNavigation': false
	};
	$.get("index.php","action=make_logs_with_details&d="+date[2]+"&m="+date[1]+"&y="+date[0], function(logEntries) {
		var empty = true;
        if (logEntries == "") {
        	// TODO: handle this case
        	$("#log_timeline_component").html("<p>Cannot fetch</p>");
        } else {
            empty = false
            var data = eval(logEntries);
            // Iterate over array, convert each object to that needed for Timeline component
            $.each(data, function() {
                this.start = new Date(this.startTime);
                this.end = new Date(this.endTime);
                this.className = mappedLogTimelineBarClass(this.avgPressureKPa);
                this.content = "" + this.avgPressureKPa + "KPa"; 
                this.group = this.stationName;
            })
            
			window.log_timeline = new links.Timeline(document.getElementById('log_timeline_component'));
    		// add listeners to widget
			links.events.addListener(log_timeline, "select", function() {
				var row = undefined;
				var sel = log_timeline.getSelection();
				if (sel.length) {
					if (sel[0].row != undefined) {
						row = sel[0].row;
					}
				}
				if (row === undefined) return;
				var content = $(".timeline-event-content")[row];
				var pid = parseInt($(content).html().substr(1)) - 1;
				//get_programs(pid);
			});
	
			$(window).on("resize",function () {window.log_timeline.redraw()});
			log_timeline.draw(data, options);
			if ($(window).width() <= 480) {
				var currRange = log_timeline.getVisibleChartRange();
				if ((currRange.end.getTime() - currRange.start.getTime()) > 6000000) log_timeline.setVisibleChartRange(currRange.start,new Date(currRange.start.getTime()+6000000))
			}
			$("#log_timeline_component .timeline-groups-text:contains('Master')").addClass("skip-numbering")
			$("#log_timeline-navigation").show()
		}
	});
	
}

function mappedLogTimelineBarClass(pressure) {
	return 'blue-gauge-3';
}

//Bind changes to the flip switches
$("select[data-role='slider']").change(function(){
    var slide = $(this);
    var type = this.name;
    var pageid = slide.closest(".ui-page-active").attr("id");
    //Find out what the switch was changed to
    var changedTo = slide.val();
    if(window.sliders[type]!==changedTo){
        window.sliders[type] = changedTo;
        if (type == "theme-select") {
            localStorage.setItem("theme",changedTo);
            $("#theme").attr("href",getThemeUrl(changedTo));
            return;
        }
        if (changedTo=="on") {
            //If chanegd to on
            if (type === "autologin") {
                if (localStorage.getItem("token") !== null) return;
                $("#login form").attr("action","javascript:grab_token('"+pageid+"')");
                $("#login .ui-checkbox").hide();
                $.mobile.changePage($("#login"));
            }
            if (type === "en") {
                $.get("index.php","action=en_on",function(result){
                    //If switch failed then change the switch back and show error
                    if (result == 0) {
                        comm_error()
                        $("#en").val("off").slider("refresh")
                    }
                });
            }
            if (type === "auto_mm") {
                $.get("index.php","action=auto_mm_on",function(result){
                    //If switch failed then change the switch back and show error
                    if (result == 0) {
                        showerror("Auto disable of manual mode was not changed. Check config.php permissions and try again.")
                        $("#auto_mm").val("off").slider("refresh")
                    }
                });
            }
            if (type === "mm" || type === "mmm") {
                $.get("index.php","action=mm_on",function(result){
                    if (result == 0) {
                        comm_error()
                        $("#mm,#mmm").val("off").slider("refresh")
                    }
                });
                //If switched to off, unhighlight all of the zones highlighted in green since all will be disabled automatically
                $("#manual a.green").removeClass("green");
                $("#mm,#mmm").val("on").slider("refresh");
            }
        } else {
            //If chanegd to off
            if (type === "autologin") {
                localStorage.removeItem(typeToKey(type));
            }
            if (type === "en") {
                $.get("index.php","action=en_off",function(result){
                    if (result == 0) {
                        comm_error()
                        $("#en").val("on").slider("refresh")
                    }
                });
            }
            if (type === "auto_mm") {
                $.get("index.php","action=auto_mm_off",function(result){
                    if (result == 0) {
                        showerror("Auto disable of manual mode was not changed. Check config.php permissions and try again.")
                        $("#auto_mm").val("on").slider("refresh")
                    }
                });
            }
            if (type === "mm" || type === "mmm") {
                $.get("index.php","action=mm_off",function(result){
                    if (result == 0) {
                        comm_error()
                        $("#mm,#mmm").val("on").slider("refresh")
                    }
                });
                //If switched to off, unhighlight all of the manual zones highlighted in green since all will be disabled automatically
                $("#manual a.green").removeClass("green");
                $("#mm,#mmm").val("off").slider("refresh");
            }
        }
    }
});

function comm_error() {
    showerror("Error communicating with OpenSprinkler. Please check your password is correct.")
}

$(document).on("pageshow",function(e,data){
    var newpage = e.target.id;
    var currpage = $(e.target);

    if (newpage == "sprinklers") {
        //Automatically update sliders on page load in settings panel
        check_auto($("#"+newpage+" select[data-role='slider']"));
    } else if (newpage == "preview") {
        get_preview();
    } else if (newpage == "logs") {
        get_logs();
    }

    currpage.find("a[href='#"+currpage.attr('id')+"-settings']").unbind("vclick").on('vclick', function (e) {
        e.preventDefault(); e.stopImmediatePropagation();
        highlight(this);
        $(".ui-page-active [id$=settings]").panel("open");
    });
    currpage.find("a[data-onclick]").unbind("vclick").on('vclick', function (e) {
        e.preventDefault(); e.stopImmediatePropagation();
        var func = $(this).data("onclick");
        highlight(this);
        eval(func);
    });

});

$(document).on("pagebeforeshow",function(e,data){
    var newpage = e.target.id;

    $.mobile.silentScroll(0);
    $("#tooltip").remove();
    if (window.interval_id !== undefined) clearInterval(window.interval_id);
    if (window.timeout_id !== undefined) clearTimeout(window.timeout_id);

    if (newpage == "sprinklers") {
        update_weather();
        $("#footer-running").html("<p style='margin:0;text-align:center;opacity:0.18'><img src='img/ajax-loader.gif' class='mini-load' /></p>");
        setTimeout(check_status,1000);
    } else {
        var title = document.title;
        document.title = "OpenSprinkler: "+title;
    }

    if (newpage == "raindelay") {
        $.get("index.php","action=get_autodelay",function(data){
            data = JSON.parse(data)
            if (data["auto_delay"]) {
                $("#auto_delay").val("on").slider("refresh")
            }
            $("#auto_delay_duration").val(data["auto_delay_duration"]).slider("refresh");
        })
    }
})

function check_status() {
    //Check if a program is running
    $.get("index.php","action=current_status",function(data){
        var footer = $("#footer-running")
        if (data === "") {
            footer.slideUp();
            return;
        }
        data = JSON.parse(data);
        if (window.interval_id !== undefined) clearInterval(window.interval_id);
        if (window.timeout_id !== undefined) clearTimeout(window.timeout_id);
        if (data.seconds != 0) update_timer(data.seconds,data.sdelay);
        footer.removeClass().addClass(data.color).html(data.line).slideDown();
    })
}

function update_timer(total,sdelay) {
    window.lastCheck = new Date().getTime();
    window.interval_id = setInterval(function(){
        var now = new Date().getTime();
        var diff = now - window.lastCheck;
        if (diff > 3000) {
            clearInterval(window.interval_id);
            $("#footer-running").html("<p style='margin:0;text-align:center;opacity:0.18'><img src='img/ajax-loader.gif' class='mini-load' /></p>");
            check_status();
        }
        window.lastCheck = now;

        if (total <= 0) {
            clearInterval(window.interval_id);
            $("#footer-running").slideUp().html("<p style='margin:0;text-align:center;opacity:0.18'><img src='img/ajax-loader.gif' class='mini-load' /></p>");
            if (window.timeout_id !== undefined) clearTimeout(window.timeout_id);
            window.timeout_id = setTimeout(check_status,(sdelay*1000));
        }
        else
            --total;
            $("#countdown").text("(" + sec2hms(total) + " remaining)");
    },1000)
}

function update_timers(sdelay) {
    if (window.interval_id !== undefined) clearInterval(window.interval_id);
    if (window.timeout_id !== undefined) clearTimeout(window.timeout_id);
    window.lastCheck = new Date().getTime();
    window.interval_id = setInterval(function(){
        var now = new Date().getTime();
        var diff = now - window.lastCheck;
        if (diff > 3000) {
            clearInterval(window.interval_id);
            get_status();
        }
        window.lastCheck = now;
        $.each(window.totals,function(a,b){
            if (b <= 0) {
                delete window.totals[a];
                if (a == "p") {
                    get_status();
                } else {
                    $("#countdown-"+a).parent("p").text("Station delay").parent("li").removeClass("green").addClass("red");
                    window.timeout_id = setTimeout(get_status,(sdelay*1000));
                }
            } else {
                if (a == "c") {
                    ++window.totals[a];
                    $("#clock-s").text(new Date(window.totals[a]*1000).toUTCString().slice(0,-4));
                } else {
                    --window.totals[a];
                    $("#countdown-"+a).text("(" + sec2hms(window.totals[a]) + " remaining)");
                }
            }
        })
    },1000)
}

function sec2hms(diff) {
    var str = "";
    var hours = parseInt( diff / 3600 ) % 24;
    var minutes = parseInt( diff / 60 ) % 60;
    var seconds = diff % 60;
    if (hours) str += pad(hours)+":";
    return str+pad(minutes)+":"+pad(seconds);
}

function check_auto(sliders){
    if (typeof(window.sliders) !== "object") window.sliders = [];
    sliders.each(function(i){
        var type = this.name;
        var item = typeToKey(type);
        if (!item) return;
        if (localStorage.getItem(item) != null) {
            window.sliders[type] = "on";
            $(this).val("on").slider("refresh");
        } else {
            window.sliders[type] = "off";
            $(this).val("off").slider("refresh");
        }
    })
}

function typeToKey(type) {
    if (type == "autologin") {
        return "token";
    } else {
        return false;
    }
}

function highlight(button) {
    $(button).addClass("ui-btn-active").delay(150).queue(function(next){
        $(this).removeClass("ui-btn-active");
        next();
    });
}

function grab_token(pageid){
    $.mobile.showPageLoadingMsg();
    var parameters = "action=gettoken&username=" + $('#username').val() + "&password=" + $('#password').val() + "&remember=true";
    $("#username, #password").val('');
    $.post("index.php",parameters,function(reply){
        $.mobile.hidePageLoadingMsg();
        if (reply == 0) {
            $.mobile.changePage($("#"+pageid));
            showerror("Invalid Login");
        } else if (reply === "") {
            $("#"+pageid+"-autologin").val("off").slider("refresh");
            window.sliders["autologin"] = "off";
            $.mobile.changePage($("#"+pageid));
        } else {
            localStorage.setItem('token',reply);
            $.mobile.changePage($("#"+pageid));
        }
        $("#login .ui-checkbox").show()
        $("#login form").attr("action","javascript:dologin()");
    }, "text");
}

function update_weather() {
    var $weather = $("#weather");
    $weather.html("<p style='margin:0;text-align:center;opacity:0.18'><img src='img/ajax-loader.gif' class='mini-load' /></p>");
    $.get("index.php","action=get_weather",function(result){
        var weather = JSON.parse(result);
        if (weather["code"] == null) {
            $("#weather-list").animate({ 
                "margin-left": "-1000px"
            },1000,function(){
                $(this).hide();
            })
            return;
        }
        $weather.html("<p title='"+weather["text"]+"' class='wicon cond"+weather["code"]+"'></p><span>"+weather["temp"]+"</span><br><span class='location'>"+weather["location"]+"</span>");
        $("#weather-list").animate({ 
            "margin-left": "0"
        },1000).show()
    })
}

function logout(){
    areYouSure("Are you sure you want to logout?", "", function() {
        $.mobile.changePage($("#login"));
        $.get("index.php", "action=logout",function(){
            localStorage.removeItem('token');
            $("body div[data-role='page']:not('.ui-page-active')").remove();
            $('.ui-page-active').one("pagehide",function(){
                $(this).remove();
            })
        });
    },gohome);
}

function gohome() {
    $.mobile.changePage($('#sprinklers'), {reverse: true});
}

function show_settings() {
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=make_settings_list",function(items){
        var list = $("#os-settings-list");
        list.html(items).trigger("create");
        if (list.hasClass("ui-listview")) list.listview("refresh");
        $.mobile.hidePageLoadingMsg();
        $.mobile.changePage($("#os-settings"));
    })    
}

function show_stations() {
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=make_stations_list",function(items){
        var list = $("#os-stations-list");
        list.html(items).trigger("create");
        if (list.hasClass("ui-listview")) list.listview("refresh");
        $.mobile.hidePageLoadingMsg();
        $.mobile.changePage($("#os-stations"));
    })    
}

function show_users() {
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=make_user_list",function(items){
        var list = $("#user-control-list");
        list.html(items).trigger("create");
        $.mobile.hidePageLoadingMsg();
        $.mobile.changePage($("#user-control"));
    })
}

function user_id_name(id) {
    var name = $("#user-"+id+" .ui-btn-text").first().text()
    name = name.replace(/ click to (collapse|expand) contents/g,"")
    return name;
}

function delete_user(id) {
    var name = user_id_name(id);
    areYouSure("Are you sure you want to delete "+name+"?", "", function() {
        $.mobile.showPageLoadingMsg();
        $.get("index.php","action=delete_user&name="+name,function(result){
            $.mobile.hidePageLoadingMsg();
            if (result == 0) {
                comm_error()
            } else {
                show_users()
            }
        })
    },show_users)
}

function add_user() {
    var nameEl = $("#name"), passEl = $("#pass");
    var name = nameEl.val(), pass = passEl.val();
    nameEl.val(""), passEl.val("");
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=add_user&name="+name+"&pass="+pass,function(result){
        $.mobile.hidePageLoadingMsg();
        if (result == 0) {
            comm_error()
        } else if (result == 3) {
            showerror("User already exists")
        } else {
            show_users()
        }
    })
}

function change_user(id) {
    var name = user_id_name(id), cpu = $("#cpu-"+id);
    var pass = cpu.val();
    cpu.val("");
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=change_user&name="+name+"&pass="+pass,function(result){
        $.mobile.hidePageLoadingMsg();
        if (result == 0) {
            comm_error()
        } else {
            showerror("Password for "+name+" has been updated")
        }
    })    
}

function get_status() {
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=make_list_status",function(items){
        var list = $("#status_list");
        items = JSON.parse(items)
        list.html(items.list);
        $("#status_header").html(items.header);
        $("#status_footer").html(items.footer);
        if (list.hasClass("ui-listview")) list.listview("refresh");
        window.totals = JSON.parse(items.totals);
        if (window.interval_id !== undefined) clearInterval(window.interval_id);
        if (window.timeout_id !== undefined) clearTimeout(window.timeout_id);
        $.mobile.hidePageLoadingMsg();
        $.mobile.changePage($("#status"));
        if (window.totals["d"] !== undefined) {
            delete window.totals["p"];
            setTimeout(get_status,window.totals["d"]*1000);
        }
        update_timers(items.sdelay);
    })
}

function change_log_timeline_date(dir) {
    var inputBox = $("#log_timeline_date");
    var date = inputBox.val();
    if (date === "") return;
    date = date.split("-");
    var nDate = new Date(date[0],date[1]-1,date[2]);
    nDate.setDate(nDate.getDate() + dir);
    var m = pad(nDate.getMonth()+1);
    var d = pad(nDate.getDate());
    inputBox.val(nDate.getFullYear() + "-" + m + "-" + d);
    $("#log_timeline_date").trigger("change");
}

function get_logs() {
    $("#logs input").blur();
    $.mobile.showPageLoadingMsg();
    var parms = "action=make_list_logs&start=" + (new Date($("#log_start").val()).getTime() / 1000) + "&end=" + ((new Date($("#log_end").val()).getTime() / 1000) + 86340);

    if ($("#log_graph").prop("checked")) {
        var grouping=$("input:radio[name='g']:checked").val();
        switch(grouping){
            case "m":
                var sort = "&sort=month";
                break;
            case "n":
                var sort = "";
                break;
            case "h":
                var sort = "&sort=hour";
                break;
            case "d":
                var sort = "&sort=dow";
                break;
        }
        $.getJSON("index.php",parms+"&type=graph"+sort,function(items){
            var is_empty = true;
            $.each(items.data,function(a,b){
                if (b.length) {
                    is_empty = false;
                    return false;
                }
            })
            if (is_empty) {
                $("#placeholder").empty().hide();
                $("#log_options").trigger("expand");
                $("#zones, #graph_sort").hide();
                $("#logs_list").show().html("<p class='center'>No entries found in the selected date range</p>");
            } else {
                $("#logs_list").empty().hide();
                $("#log_timeline_panel").hide();
            	$("#log_timeline_component").hide();
				$("#log_timeline-navigation").hide();
				
                var state = ($(window).height() > 680) ? "expand" : "collapse";
                setTimeout(function(){$("#log_options").trigger(state)},100);
                $("#placeholder").show();
                var zones = $("#zones");
                var freshLoad = zones.find("table").length;
                zones.show(); $("#graph_sort").show();
                if (!freshLoad) {
                    var output = '<div onclick="scrollZone(this);" class="ui-icon ui-icon-arrow-l" id="graphScrollLeft"></div><div onclick="scrollZone(this);" class="ui-icon ui-icon-arrow-r" id="graphScrollRight"></div><table style="font-size:smaller"><tbody><tr>', k=0;
                    for (var i=0; i<items.stations.length; i++) {
                        output += '<td onclick="javascript:toggleZone(this)" class="legendColorBox"><div style="border:1px solid #ccc;padding:1px"><div style="width:4px;height:0;overflow:hidden"></div></div></td><td onclick="javascript:toggleZone(this)" id="z'+i+'" zone_num='+i+' name="'+items.stations[i] + '" class="legendLabel">'+items.stations[i]+'</td>';
                        k++;
                    }
                    output += '</tr></tbody></table>';
                    zones.empty().append(output).trigger('create');
                }
                window.plotdata = items.data;
                seriesChange();
                var i = 0;
                if (!freshLoad) {
                    zones.find("td.legendColorBox div div").each(function(a,b){
                        var border = $($("#placeholder .legendColorBox div div").get(i)).css("border");
                        //Firefox and IE fix
                        if (border == "") {
                            border = $($("#placeholder .legendColorBox div div").get(i)).attr("style").split(";");
                            $.each(border,function(a,b){
                                var c = b.split(":");
                                if (c[0] == "border") {
                                    border = c[1];
                                    return false;
                                }
                            })
                        }
                        $(b).css("border",border);
                        i++;
                    })
                    showArrows();
                }
            }
            $.mobile.hidePageLoadingMsg();
        });
        return;
    }
	
//	'Timeline' radio button is selected
	if ($('#log_timeline').prop("checked")) {
		$.get("index.php",parms+"&type=timeline", function(items) {
        	$("#placeholder").empty().hide();
        	var list = $("#logs_list");
        	$("#zones, #graph_sort").hide(); list.show();
	        if (items.length == 154) {
    	        $("#log_options").trigger("expand");
        	    list.html("<p class='center'>Timeline logs are not yet implemented.</p>");
        	} else {
        		// No options supported for timeline
            	$("#log_options").trigger("collapse");
            	
            	// hide list
            	list.empty().hide();
            	$("#log_timeline_panel").show();
            	$("#log_timeline_component").show();
				$("#log_timeline-navigation").show()
        		
        		get_logs_timeline();
    			
			} // end if (items.length == 154) else case
           	$.mobile.hidePageLoadingMsg();
		});
		return;
	}
//	End of Timeline case
	
//	Otherwise 'Table' radio button is selected
    $.get("index.php",parms,function(items) {
    	// hide non-table display components
        $("#placeholder").empty().hide();
        $("#log_timeline_panel").hide();
        $("#log_timeline_component").hide();
		$("#log_timeline-navigation").hide();
		
        var list = $("#logs_list");
        $("#zones, #graph_sort").hide(); list.show();
        if (items.length == 154) {
            $("#log_options").trigger("expand");
            list.html("<p class='center'>No entries found in the selected date range</p>");
        } else {
            $("#log_options").trigger("collapse");
            list.html(items).trigger("create");
        }
        $.mobile.hidePageLoadingMsg();
    })
}

function scrollZone(dir) {
    dir = ($(dir).attr("id") == "graphScrollRight") ? "+=" : "-=";
    var zones = $("#zones");
    var w = zones.width();
    zones.animate({scrollLeft: dir+w})
}

function toggleZone(zone) {
    zone = $(zone);
    if (zone.hasClass("legendColorBox")) {
        zone.find("div div").toggleClass("hideZone");
        zone.next().toggleClass("unchecked");
    } else if (zone.hasClass("legendLabel")) {
        zone.prev().find("div div").toggleClass("hideZone");
        zone.toggleClass("unchecked");
    }
    seriesChange();
}

function showArrows() {
    var zones = $("#zones");
    var height = zones.height(), sleft = zones.scrollLeft();
    if (sleft > 13) {
        $("#graphScrollLeft").show().css("margin-top",(height/2)-12.5)
    } else {
        $("#graphScrollLeft").hide();
    }
    var total = zones.find("table").width(), container = zones.width();
    if ((total-container) > 0 && sleft < ((total-container) - 13)) {
        $("#graphScrollRight").show().css({
            "margin-top":(height/2)-12.5,
            "left":container
        })
    } else {
        $("#graphScrollRight").hide();
    }
}

function seriesChange() {
//Originally written by Richard Zimmerman
    var grouping=$("input:radio[name='g']:checked").val();
    var pData = [];
    $("td[zone_num]:not('.unchecked')").each(function () {
        var key = $(this).attr("zone_num");
        if (!window.plotdata[key].length) window.plotdata[key]=[[0,0]];
        if (key && window.plotdata[key]) {
            if ((grouping == 'h') || (grouping == 'm') || (grouping == 'd'))
                pData.push({
                    data:window.plotdata[key],
                    label:$(this).attr("name"),
                    color:parseInt(key),
                    bars: { order:key, show: true, barWidth:0.08}
                });
            else if (grouping == 'n')
                pData.push({
                    data:plotdata[key],
                    label:$(this).attr("name"),
                    color:parseInt(key),
                    lines: { show:true }
                });
        }
    });
    if (grouping=='h')
        $.plot($('#placeholder'), pData, {
            grid: { hoverable: true },
            yaxis: {min: 0, tickFormatter: function(val, axis) { return val < axis.max ? Math.round(val*100)/100 : "min";} },
            xaxis: { tickDecimals: 0, tickSize: 1 }
        });
    else if (grouping=='d')
        $.plot($('#placeholder'), pData, {
            grid: { hoverable: true },
            yaxis: {min: 0, tickFormatter: function(val, axis) { return val < axis.max ? Math.round(val*100)/100 : "min";} },
            xaxis: { tickDecimals: 0, min: -0.4, max: 6.4, 
            tickFormatter: function(v) { var dow=["Sun","Mon","Tue","Wed","Thr","Fri","Sat"]; return dow[v]; } }
        });
    else if (grouping=='m')
        $.plot($('#placeholder'), pData, {
            grid: { hoverable: true },
            yaxis: {min: 0, tickFormatter: function(val, axis) { return val < axis.max ? Math.round(val*100)/100 : "min";} },
            xaxis: { tickDecimals: 0, min: 0.6, max: 12.4, tickSize: 1,
            tickFormatter: function(v) { var mon=["","Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"]; return mon[v]; } }
        });
    else if (grouping=='n') {
        var minval = new Date($('#log_start').val()).getTime();
        var maxval = new Date($('#log_end').val());
        maxval.setDate(maxval.getDate() + 1);
        $.plot($('#placeholder'), pData, {
            grid: { hoverable: true },
            yaxis: {min: 0, tickFormatter: function(val, axis) { return val < axis.max ? Math.round(val*100)/100 : "min";} },
            xaxis: { mode: "time", min:minval, max:maxval.getTime()}
        });
    }
}

function get_manual() {
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=make_list_manual",function(items){
        var list = $("#mm_list");
        list.html(items);
        if (list.hasClass("ui-listview")) list.listview("refresh");
        $.mobile.hidePageLoadingMsg();
        $.mobile.changePage($("#manual"));
    })
}

function get_runonce() {
    $.mobile.showPageLoadingMsg();
    $.getJSON("index.php","action=make_runonce",function(items){
        window.rprogs = items.progs;
        var list = $("#runonce_list"), i=0;
        list.html(items.page);

        var progs = "<select data-mini='true' name='rprog' id='rprog'><option value='s'>Quick Programs</option>";
        var data = JSON.parse(localStorage.getItem("runonce"));
        if (data !== null) {
            list.find(":input[data-type='range']").each(function(a,b){
                $(b).val(data[i]/60);
                i++;
            })
            window.rprogs["l"] = data;
            progs += "<option value='l' selected='selected'>Last Used Program</option>";
        }
        for (i=0; i<items.progs.length; i++) {
            progs += "<option value='"+i+"'>Program "+(i+1)+"</option>";
        };
        progs += "</select>";
        $("#runonce_list p").after(progs);
        $("#rprog").change(function(){
            var prog = $(this).val();
            if (prog == "s") {
                reset_runonce()
                return;
            }
            if (window.rprogs[prog] == undefined) return;
            fill_runonce(list,window.rprogs[prog]);
        })

        list.trigger("create");
        $.mobile.hidePageLoadingMsg();
        $.mobile.changePage($("#runonce"));
    })
}

function fill_runonce(list,data){
    var i=0;
    list.find(":input[data-type='range']").each(function(a,b){
        $(b).val(data[i]/60).slider("refresh");
        i++;
    })
}

function get_preview() {
    $("#timeline").html("");
    $("#timeline-navigation").hide()
    var date = $("#preview_date").val();
    if (date === "") return;
    date = date.split("-");
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=get_preview&d="+date[2]+"&m="+date[1]+"&y="+date[0],function(items){
        var empty = true;
        if (items == "") {
            $("#timeline").html("<p align='center'>No stations set to run on this day.</p>")
        } else {
            empty = false
            var data = eval("["+items.substring(0, items.length - 1)+"]");
            $.each(data, function(){
                this.start = new Date(date[0],date[1]-1,date[2],0,0,this.start);
                this.end = new Date(date[0],date[1]-1,date[2],0,0,this.end);
            })
            var options = {
                'width':  '100%',
                'editable': false,
                'axisOnTop': true,
                'eventMargin': 10,
                'eventMarginAxis': 0,
                'min': new Date(date[0],date[1]-1,date[2],0),
                'max': new Date(date[0],date[1]-1,date[2],24),
                'selectable': true,
                'showMajorLabels': false,
                'zoomMax': 1000 * 60 * 60 * 24,
                'zoomMin': 1000 * 60 * 60,
                'groupsChangeable': false,
                'showNavigation': false
            };

            window.timeline = new links.Timeline(document.getElementById('timeline'));
            links.events.addListener(timeline, "select", function(){
                var row = undefined;
                var sel = timeline.getSelection();
                if (sel.length) {
                    if (sel[0].row != undefined) {
                        row = sel[0].row;
                    }
                }
                if (row === undefined) return;
                var content = $(".timeline-event-content")[row];
                var pid = parseInt($(content).html().substr(1)) - 1;
                get_programs(pid);
            });
            $(window).on("resize",timeline_redraw);
            timeline.draw(data, options);
            if ($(window).width() <= 480) {
                var currRange = timeline.getVisibleChartRange();
                if ((currRange.end.getTime() - currRange.start.getTime()) > 6000000) timeline.setVisibleChartRange(currRange.start,new Date(currRange.start.getTime()+6000000))
            }
            $("#timeline .timeline-groups-text:contains('Master')").addClass("skip-numbering")
            $("#timeline-navigation").show()
        }
        $.mobile.hidePageLoadingMsg();
    })
}

function timeline_redraw() {
    window.timeline.redraw();
}

function changeday(dir) {
    var inputBox = $("#preview_date");
    var date = inputBox.val();
    if (date === "") return;
    date = date.split("-");
    var nDate = new Date(date[0],date[1]-1,date[2]);
    nDate.setDate(nDate.getDate() + dir);
    var m = pad(nDate.getMonth()+1);
    var d = pad(nDate.getDate());
    inputBox.val(nDate.getFullYear() + "-" + m + "-" + d);
    get_preview();
}

function get_programs(pid) {
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=make_all_programs",function(items){
        var list = $("#programs_list");
        list.html(items);
        if (typeof pid !== 'undefined') {
            if (pid === false) {
                $.mobile.silentScroll(0)
            } else {
                $("#programs fieldset[data-collapsed='false']").attr("data-collapsed","true");
                $("#program-"+pid).attr("data-collapsed","false")
            }
        }
        $("#programs input[name^='rad_days']").change(function(){
            var progid = $(this).attr('id').split("-")[1], type = $(this).val().split("-")[0], old;
            type = type.split("_")[1];
            if (type == "n") {
                old = "week"
            } else {
                old = "n"
            }
            $("#input_days_"+type+"-"+progid).show()
            $("#input_days_"+old+"-"+progid).hide()
        })

        $("#programs [id^='submit-']").click(function(){
            submit_program($(this).attr("id").split("-")[1]);
        })
        $("#programs [id^='s_checkall-']").click(function(){
            var id = $(this).attr("id").split("-")[1]
            $("[id^='station_'][id$='-"+id+"']").prop("checked",true).checkboxradio("refresh");
        })
        $("#programs [id^='s_uncheckall-']").click(function(){
            var id = $(this).attr("id").split("-")[1]
            $("[id^='station_'][id$='-"+id+"']").prop("checked",false).checkboxradio("refresh");
        })
        $("#programs [id^='delete-']").click(function(){
            delete_program($(this).attr("id").split("-")[1]);
        })
        $.mobile.changePage($("#programs"));
        $.mobile.hidePageLoadingMsg();
        $("#programs").trigger("create");
        update_program_header();
    })
}

function update_program_header() {
    $("#programs_list").find("[id^=program-]").each(function(a,b){
        var item = $(b)
        var id = item.attr('id').split("program-")[1]
        var en = $("#en-"+id).is(":checked")
        if (en) {
            item.find(".ui-collapsible-heading-toggle").removeClass("red")
        } else {
            item.find(".ui-collapsible-heading-toggle").addClass("red")
        }
    })
}

function add_program() {
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=fresh_program",function(items){
        var list = $("#newprogram");
        list.html(items);
        $("#addprogram input[name^='rad_days']").change(function(){
            var progid = "new", type = $(this).val().split("-")[0], old;
            type = type.split("_")[1];
            if (type == "n") {
                old = "week"
            } else {
                old = "n"
            }
            $("#input_days_"+type+"-"+progid).show()
            $("#input_days_"+old+"-"+progid).hide()
        })
        $("#addprogram [id^='s_checkall-']").click(function(){
            $("[id^='station_'][id$='-new']").prop("checked",true).checkboxradio("refresh");
        })
        $("#addprogram [id^='s_uncheckall-']").click(function(){
            $("[id^='station_'][id$='-new']").prop("checked",false).checkboxradio("refresh");
        })
        $("#addprogram [id^='submit-']").click(function(){
            submit_program("new");
        })
        $.mobile.changePage($("#addprogram"));
        $.mobile.hidePageLoadingMsg();
        $("#addprogram").trigger("create");
    })    
}

function delete_program(id) {
    areYouSure("Are you sure you want to delete program "+(parseInt(id)+1)+"?", "", function() {
        $.mobile.showPageLoadingMsg();
        $.get("index.php","action=delete_program&pid="+id,function(result){
            $.mobile.hidePageLoadingMsg();
            if (result == 0) {
                comm_error()
            } else {
                get_programs(false)
            }
        })
    },get_programs)
}

function reset_runonce() {
    $("#runonce").find(":input[data-type='range']").val(0).slider("refresh")
}

function submit_program(id) {
    var program = [], days=[0,0]
    program[0] = ($("#en-"+id).is(':checked')) ? 1 : 0

    if($("#days_week-"+id).is(':checked')) {
        for(i=0;i<7;i++) {if($("#d"+i+"-"+id).is(':checked')) {days[0] |= (1<<i); }}
        if($("#days_odd-"+id).is(':checked')) {days[0]|=0x80; days[1]=1;}
        else if($("#days_even-"+id).is(':checked')) {days[0]|=0x80; days[1]=0;}
    } else if($("#days_n-"+id).is(':checked')) {
        days[1]=parseInt($("#every-"+id).val(),10);
        if(!(days[1]>=2&&days[1]<=128)) {showerror("Error: Interval days must be between 2 and 128.");return;}
        days[0]=parseInt($("#starting-"+id).val(),10);
        if(!(days[0]>=0&&days[0]<days[1])) {showerror("Error: Starting in days wrong.");return;}
        days[0]|=0x80;
    }
    program[1] = days[0]
    program[2] = days[1]

    var start = $("#start-"+id).val().split(":")
    program[3] = parseInt(start[0])*60+parseInt(start[1])
    var end = $("#end-"+id).val().split(":")
    program[4] = parseInt(end[0])*60+parseInt(end[1])

    if(!(program[3]<program[4])) {showerror("Error: Start time must be prior to end time.");return;}

    program[5] = parseInt($("#interval-"+id).val())
    program[6] = $("#duration-"+id).val() * 60

    var sel = $("[id^=station_][id$=-"+id+"]")
    var total = sel.length
    var nboards = total / 8


    var stations=[0],station_selected=0,bid, sid;
    for(bid=0;bid<nboards;bid++) {
        stations[bid]=0;
        for(s=0;s<8;s++) {
            sid=bid*8+s;
            if($("#station_"+sid+"-"+id).is(":checked")) {
                stations[bid] |= 1<<s; station_selected=1;
            }
        }
    }
    if(station_selected==0) {showerror("Error: You have not selected any stations.");return;}
    program = JSON.stringify(program.concat(stations))
    $.mobile.showPageLoadingMsg()
    if (id == "new") {
        $.get("index.php","action=update_program&pid=-1&data="+program,function(result){
            $.mobile.hidePageLoadingMsg()
            get_programs()
            if (result == 0) {
                setTimeout(comm_error,400)
            } else {
                setTimeout(function(){showerror("Program added successfully")},400)
            }
        });
    } else {
        $.get("index.php","action=update_program&pid="+id+"&data="+program,function(result){
            $.mobile.hidePageLoadingMsg()
            if (result == 0) {
                comm_error()
            } else {
                update_program_header();
                showerror("Program has been updated")
            }
        });
    }
}

function submit_settings() {
    var opt = {}, invalid = false;
    $("#os-settings-list").find(":input").each(function(a,b){
        var $item = $(b), id = $item.attr('id'), data = $item.val();
        switch (id) {
            case "o1":
                var tz = data.split(":")
                tz[0] = parseInt(tz[0],10);
                tz[1] = parseInt(tz[1],10);
                tz[1]=(tz[1]/15>>0)/4.0;tz[0]=tz[0]+(tz[0]>=0?tz[1]:-tz[1]);
                data = ((tz[0]+12)*4)>>0
                break;
            case "o16":
            case "o21":
            case "o22":
            case "o25":
                data = $item.is(":checked") ? 1 : 0
                if (!data) return true
                break;
        }
        opt[id] = encodeURIComponent(data)
    })
    if (invalid) return
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=submit_options&options="+JSON.stringify(opt),function(result){
        $.mobile.hidePageLoadingMsg();
        gohome();
        if (result == 0) {
            comm_error()
        } else {
            showerror("Settings have been saved")
        }
    })
}

function submit_stations() {
    var names = {}, invalid = false,v="";bid=0,s=0,m={},masop="";
    $("#os-stations-list").find(":input,p[id^='um_']").each(function(a,b){
        var $item = $(b), id = $item.attr('id'), data = $item.val();
        switch (id) {
            case "edit_station_" + id.slice("edit_station_".length):
                id = "s" + id.split("_")[2]
                if (data.length > 16) {
                    invalid = true
                    $item.focus()
                    showerror("Station name must be 16 characters or less")
                    return false
                }
                names[id] = encodeURIComponent(data)
                return true;
                break;
            case "um_" + id.slice("um_".length):
                v = ($item.is(":checked") || $item.prop("tagName") == "P") ? "1".concat(v) : "0".concat(v);
                s++;
                if (parseInt(s/8) > bid) {
                    m["m"+bid]=parseInt(v,2); bid++; s=0; v="";
                }
                return true;
                break;
        }
    })
    m["m"+bid]=parseInt(v,2);
    if ($("[id^='um_']").length) masop = "&masop="+JSON.stringify(m);
    if (invalid) return
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=submit_stations&names="+JSON.stringify(names)+masop,function(result){
        $.mobile.hidePageLoadingMsg();
        gohome();
        if (result == 0) {
            comm_error()
        } else {
            showerror("Stations have been updated")
        }
    })
}

function submit_runonce() {
    var runonce = []
    $("#runonce").find(":input[data-type='range']").each(function(a,b){
        runonce.push(parseInt($(b).val())*60)
    })
    runonce.push(0);
    localStorage.setItem("runonce",JSON.stringify(runonce));
    $.get("index.php","action=runonce&data="+JSON.stringify(runonce),function(result){
        if (result == 0) {
            comm_error()
        } else {
            showerror("Run-once program has been scheduled")
        }
    })
    gohome();
}

function toggle(anchor) {
    if ($("#mm").val() == "off") return;
    var $list = $("#mm_list");
    var $anchor = $(anchor);
    var $listitems = $list.children("li:not(li.ui-li-divider)");
    var $item = $anchor.closest("li:not(li.ui-li-divider)");
    var currPos = $listitems.index($item) + 1;
    var total = $listitems.length;
    if ($anchor.hasClass("green")) {
        $.get("index.php","action=spoff&zone="+currPos,function(result){
            if (result == 0) {
                $anchor.addClass("green");
                comm_error()
            }
        })
        $anchor.removeClass("green");
    } else {
        $.get("index.php","action=spon&zone="+currPos,function(result){
            if (result == 0) {
                $anchor.removeClass("green");
                comm_error()
            }
        })
        $anchor.addClass("green");
    }
}

function raindelay() {
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=raindelay&delay="+$("#delay").val(),function(result){
        $.mobile.hidePageLoadingMsg();
        gohome();
        if (result == 0) {
            comm_error()
        } else {
            showerror("Rain delay has been successfully set")
        }
    });
}

function auto_raindelay() {
    $.mobile.showPageLoadingMsg();
    var params = {
        "auto_delay": $("#auto_delay").val(),
        "auto_delay_duration": $("#auto_delay_duration").val()
    }
    params = JSON.stringify(params)
    $.get("index.php","action=submit_autodelay&autodelay="+params,function(result){
        $.mobile.hidePageLoadingMsg();
        gohome();
        if (result == 2) {
            showerror("Auto-delay changes were not saved. Check config.php permissions and try again.");
        } else {
            showerror("Auto-delay changes have been saved")
        }
    })
}

function clear_logs() {
    areYouSure("Are you sure you want to clear all your log data?", "", function() {
        $.mobile.showPageLoadingMsg()
        $.get("index.php","action=clear_logs",function(result){
            $.mobile.hidePageLoadingMsg()
            gohome();
            if (result == 0) {
                comm_error()
            } else {
                showerror("Logs have been cleared")
            }
        });
    },gohome);    
}

function rbt() {
    areYouSure("Are you sure you want to reboot OpenSprinkler?", "", function() {
        $.mobile.showPageLoadingMsg()
        $.get("index.php","action=rbt",function(result){
            $.mobile.hidePageLoadingMsg()
            gohome();
            if (result == 0) {
                comm_error()
            } else {
                showerror("OpenSprinkler is rebooting now")
            }
        });
    },gohome);
}

function rsn() {
    areYouSure("Are you sure you want to stop all stations?", "", function() {
        $.mobile.showPageLoadingMsg()
        $.get("index.php","action=rsn",function(result){
            $.mobile.hidePageLoadingMsg()
            gohome();
            if (result == 0) {
                comm_error()
            } else {
                showerror("All stations have been stopped")
            }
        });
    },gohome);
}

function export_config() {
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=export_config",function(data){
        $.mobile.hidePageLoadingMsg();
        $("#sprinklers-settings").panel("close")
        if (data === "") {
            comm_error()
        } else {
            localStorage.setItem("backup", data);
            showerror("Backup saved to your device");
        }
    })
}

function import_config() {
    var data = localStorage.getItem("backup");
    if (data === null) {
        showerror("No backup available on this device");
        return;
    }

    areYouSure("Are you sure you want to restore the configuration?", "", function() {
        $.mobile.showPageLoadingMsg();
        $.get("index.php","action=import_config&data="+data,function(reply){
            $.mobile.hidePageLoadingMsg();
            gohome();
            if (reply == 0) {
                comm_error()
            } else {
                showerror("Backup restored to your device");
            }
        })
    },gohome);
}

function areYouSure(text1, text2, callback, callback2) {
    $("#sure .sure-1").text(text1);
    $("#sure .sure-2").text(text2);
    $("#sure .sure-do").unbind("click.sure").on("click.sure", function() {
        callback();
    });
    $("#sure .sure-dont").unbind("click.sure").on("click.sure", function() {
        callback2();
    });
    $.mobile.changePage("#sure");
}

function showTooltip(x, y, contents, color) {
    $('<div id="tooltip">' + contents + '</div>').css( {
        position: 'absolute',
        display: 'none',
        top: y + 5,
        left: x + 5,
        border: '1px solid #fdd',
        padding: '2px',
        'background-color': color,
        opacity: 0.80
    }).appendTo("body").fadeIn(200);
}