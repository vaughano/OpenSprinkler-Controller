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
if (!is_auth()) {exit();}

#Echo token so browser can cache it for automatic logins
if (isset($_SESSION['sendtoken']) && $_SESSION['sendtoken']) { echo "localStorage.setItem('token', '".$_SESSION['token']."');\n"; $_SESSION['sendtoken'] = false; }
?>
$(document).one("pageinit","#sprinklers", function(){
    $.mobile.hidePageLoadingMsg();
    $.mobile.changePage($("#sprinklers"));
});
$(document).on("swiperight swipeleft", function(e){
    eventtype = e.type;
    page = $(e.target).closest(".ui-page-active");
    pageid = page.attr("id");
    panel = page.find("[id$=settings]");

    if (panel.length != 0 && !panel.hasClass("ui-panel-closed")) {
        return false;
    }

    if (eventtype == "swiperight" && pageid == "sprinklers") {
        if (panel.length == 0) return;
        panel.panel("open");
    }
});

$("select[data-role='slider']").change(function(){
    var slide = $(this);
    var type = this.name;
    var pageid = slide.closest(".ui-page-active").attr("id");
    var changedTo = slide.val();
    if(window.sliders[type]!==changedTo){
        if (changedTo=="on") {
            if (type === "autologin") {
                if (localStorage.getItem("token") != null) return;
                $("#login form").attr("action","javascript:grab_token('"+pageid+"')");
                $.mobile.changePage($("#login"));
            }
            if (type === "en") {
                $.get("index.php","action=en_on");
            }
            if (type === "mm" || type === "mmm") {
                $.get("index.php","action=mm_on");
                $("#mm,#mmm").val("on").slider("refresh");
            }
        } else {
            if (type === "autologin") {
                localStorage.removeItem(typeToKey(type));
            }
            if (type === "en") {
                $.get("index.php","action=en_off");
            }
            if (type === "mm" || type === "mmm") {
                $.get("index.php","action=mm_off");
                $("#mm,#mmm").val("off").slider("refresh");
            }
        }
    }
});

$("#sprinklers,#status").on("pagebeforeshow",function(e,data){
    var newpage = e.target.id;
     
    if (newpage == "sprinklers") {
        new_tip();
    }
});

$(document).on('pageinit', function (e, data) {
    var newpage = e.target.id;

    if (newpage == "sprinklers" || newpage == "status" || newpage == "manual" || newpage == "logs" || newpage == "programs") {
        currpage = $(e.target);

        currpage.find("a[data-rel=back]").bind('vclick', function (e) {
            e.preventDefault(); e.stopImmediatePropagation();
            highlight(this);
            history.back();
        })
        currpage.find("a[data-rel=close]").bind('vclick', function (e) {
            e.preventDefault(); e.stopImmediatePropagation();
            highlight(this);
            $(".ui-page-active [id$=settings]").panel("close");
        })
        currpage.find("a[href='#"+currpage.attr('id')+"-settings']").bind('vclick', function (e) {
            e.preventDefault(); e.stopImmediatePropagation();
            highlight(this);
            $(".ui-page-active [id$=settings]").panel("open");
        });
        currpage.find("a[href^=javascript\\:]").bind('vclick', function (e) {
            e.preventDefault(); e.stopImmediatePropagation();
            var func = $(this).attr("href").split("javascript:")[1];
            highlight(this);
            eval(func);
        });
    }
});

$(document).on("pageshow",function(e,data){
    newpage = e.target.id;

    if (newpage == "sprinklers") {
        check_auto($("#"+newpage+" select[data-role='slider']"));
    }

});

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
    var parameters = "action=gettoken&username=" + $('#username').val() + "&password=" + $('#password').val() + "&remember=" + $('#remember').is(':checked');
    if (!$('#remember').is(':checked')) {
        $("#"+pageid+"-autologin").val("off").slider("refresh");
        window.sliders["autologin"] = "off";
        $.mobile.changePage($("#"+pageid));
        return;
    }
    $.post("index.php",parameters,function(reply){
        $.mobile.hidePageLoadingMsg();
        if (reply == 0) {
            showerror("Invalid Login");
            $.mobile.changePage($("#"+pageid));
        } else if (reply === "") {
            $("#"+pageid+"-autologin").val("off").slider("refresh");
            window.sliders["autologin"] = "off";
            $.mobile.changePage($("#"+pageid));
        } else {
            localStorage.setItem('token',reply);
            $.mobile.changePage($("#"+pageid));
        }
    }, "text");
    $("#login form").attr("action","javascript:dologin()");
}

function new_tip() {
    var tips = [
        "Be sure to disable manual mode otherwise programs will not run",
        "The status page highlights active sprinklers in green and inactive in red",
        "Logs allow you to view historical activity of your sprinkler system",
        "Slide to the right to expose the settings panel"
    ];
    var i = Math.floor((Math.random()*tips.length));
    $("#tip").html("Tip: "+tips[i]);
}

function logout(){
    if (confirm('Are you sure you want to logout?')) {
        $.get("index.php", "action=logout",function(){
            localStorage.removeItem('token');
            $("#container div[data-role='page']:not('.ui-page-active')").remove();
            $('.ui-page-active').one("pagehide",function(){
                $(this).remove();
            })
            $.mobile.changePage($("#login"));
        });
    }
}

function gohome() {
    $.mobile.changePage($('#sprinklers'), {reverse: true, transition: "slidefade"});
}

function get_status() {
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=make_list_status",function(items){
        list = $("#status_list");
        list.html(items);
        if (list.hasClass("ui-listview")) list.listview("refresh");
        $.mobile.hidePageLoadingMsg();
        $.mobile.changePage($("#status"));
    })
}

function get_programs() {
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=make_list_programs",function(items){
        list = $("#programs_list");
        list.html(items);
        $("input[name^='rad_days']").change(function(){
            progid = $(this).attr('id').split("-")[1];
            type = $(this).val().split("-")[0];
            type = type.split("_")[1];
            if (type == "n") {
                old = "week"
            } else {
                old = "n"
            }
            $("#input_days_"+type+"-"+progid).show()
            $("#input_days_"+old+"-"+progid).hide()
        })

        //Stupidest bug fix ever but it works...
        $("#programs [type='checkbox']").change(function(){
            window.scrollTo(1,1)
        })
        $("#programs [id^='submit-']").click(function(){
            submit_program($(this).attr("id").split("-")[1]);
        })
        $("#programs [id^='delete-']").click(function(){
            delete_program($(this).attr("id").split("-")[1]);
        })
        $.mobile.hidePageLoadingMsg();
        $("#programs").trigger("create");
        $.mobile.changePage($("#programs"));
    })
}

function add_program() {
    $.mobile.showPageLoadingMsg();
    $.get("index.php","action=fresh_program",function(items){
        list = $("#newprogram");
        list.html(items);
        $("#addprogram input[name^='rad_days']").change(function(){
            progid = "new";
            type = $(this).val().split("-")[0];
            type = type.split("_")[1];
            if (type == "n") {
                old = "week"
            } else {
                old = "n"
            }
            $("#input_days_"+type+"-"+progid).show()
            $("#input_days_"+old+"-"+progid).hide()
        })
        //Stupidest bug fix ever but it works...
        $("#addprogram [type='checkbox']").change(function(){
            window.scrollTo(1,1)
        })
        $("#addprogram [id^='submit-']").click(function(){
            submit_program("new");
        })
        $.mobile.hidePageLoadingMsg();
        $("#addprogram").trigger("create");
        $.mobile.changePage($("#addprogram"));
    })    
}

function delete_program(id) {
    if(!confirm("Are you sure you want to delete program "+(parseInt(id)+1)+"?")) return false;
    $.get("index.php","action=delete_program&pid="+id)
    get_programs()
}

function submit_program(id) {
    program = []
    days=[0,0]
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

    start = $("#start-"+id).val().split(":")
    program[3] = parseInt(start[0])*60+parseInt(start[1])
    end = $("#end-"+id).val().split(":")
    program[4] = parseInt(end[0])*60+parseInt(end[1])

    if(!(program[3]<program[4])) {showerror("Error: Start time must be prior to end time.");return;}

    //Do not understand what this does so I have not displayed it and just set it to the default for now (come back later...)
    program[5] = 240

    program[6] = $("#duration-"+id).val() * 60

    sel = $("[id^=station_][id$=-"+id+"]")
    total = sel.length
    nboards = total / 8


    var stations=[0],station_selected=0,bid;
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
        $.get("index.php","action=update_program&pid=-1&data="+program,function(data){
            $.mobile.hidePageLoadingMsg()
            get_programs()
        });
    } else {
        $.get("index.php","action=update_program&pid="+id+"&data="+program,function(data){
            $.mobile.hidePageLoadingMsg()
        });
    }
}

function toggle() {
    if ($("#mm").val() == "off") return;
    var $list = $("#mm_list");
    $anchor = $list.find(".ui-btn-active");
    $listitems = $list.children("li:not(li.ui-li-divider)");
    $item = $anchor.closest("li:not(li.ui-li-divider)");
    var currPos = $listitems.index($item) + 1;
    var total = $listitems.length;
    if ($anchor.hasClass("green")) {
        $.get("index.php","action=spoff&zone="+currPos)
        $anchor.removeClass("green");
    } else {
        $.get("index.php","action=spon&zone="+currPos)
        $anchor.addClass("green");
    }
}

function raindelay() {
    $.get("index.php","action=raindelay&delay="+$("#delay").val());
    gohome();
}

function rbt() {
    $.get("index.php","action=rbt");
}

function rsn() {
    $.get("index.php","action=rsn");
}