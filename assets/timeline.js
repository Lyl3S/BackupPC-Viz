    function GetFileName(){
      //this gets the full url
      var url = document.location.href;
      //this removes the anchor at the end, if there is one
      url = url.substring(0, (url.indexOf("#") == -1) ? url.length : url.indexOf("#"));
      //this removes the query after the file name, if there is one
      url = url.substring(0, (url.indexOf("?") == -1) ? url.length : url.indexOf("?"));
      //this removes everything before the last slash in the path
      url = url.substring(url.lastIndexOf("/") + 1, url.length);
      //return
      if(url.length == 0){
        url = "index.html";
      }
      return url;
    }

    function CreateNavButtons() {
      var url = GetFileName();

      var locDate = 0;
      var locDaily = url.search("daily-");
      if (locDaily > 0) {
        var locDate = locDaily + 6;
        var dateDiff = 1;
      }
      var locWeekly = url.search("weekly-");
      if (locWeekly > 0) {
        var locDate = locWeekly + 7;
        var dateDiff = 7
      }
      if (locDate > 0) {
        var dateString = url.substr(locDate, 10);
        var prevDate = new Date(dateString);
        var nextDate = new Date(dateString);

        prevDate.setHours(23); // Set hours so that date doesn't skip when switching from daylight savings time
        prevDate.setDate(prevDate.getDate()-dateDiff);
        var prevDateString = prevDate.toISOString();
        var prevDateUrl = url.replace(dateString, prevDateString.substr(0, 10));

        nextDate.setHours(23); // Set hours so that date doesn't skip when switching to daylight savings time
        nextDate.setDate(nextDate.getDate()+dateDiff);
        var nextDateString = nextDate.toISOString();
        var nextDateUrl = url.replace(dateString, nextDateString.substr(0, 10));

        document.getElementById("nav-buttons").innerHTML =
          '<a href="' + prevDateUrl + '"><img src="/timelines/assets/prev.png" border="0"></a>' +
          '<a href="' + nextDateUrl + '"><img src="/timelines/assets/next.png" border="0"></a>' ;
      }
    }

    function setLineHeight() {
      $("#vertical").height($("#lineholder").height());
    }
 
    $(document).ready(function(){
      CreateNavButtons();
    });

    document.getElementsByTagName("BODY")[0].onresize = function() {setLineHeight()};
      
    $( window ).load(function() {
      $("#vertical").height($("#lineholder").height());
    });

    $('#lineholder').on('mousemove', null, [$('#vertical')],function(e){
//      e.data[0].css('left', e.offsetX==undefined?e.originalEvent.layerX:e.offsetX);
      e.data[0].css('left', e.pageX);
    });

    $('#lineholder').on('mouseenter', null, [$('#vertical')], function(e){
      e.data[0].show();
    }).on('mouseleave', null, [$('#vertical')], function(e){
        e.data[0].hide();
    });

