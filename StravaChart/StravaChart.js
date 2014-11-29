var	stravaChartR,
	currentWindowWidth = $(window).width(),
	dataJson,
	curWeek,
	nextWeek,
	prevWeek;

/**
 * drawStravaChart creates a SVG graphic using the Raphael library of
 * weekly training activities downloaded from Strava.
 */
var drawStravaChart = function() {
	if (currentWindowWidth <= 767) {
		var	axisx = ["Total"],
			width = 250,
			mobile = true;
	} else {
		var	axisx = ["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday",""],
		    width = 600,
		    mobile = false;
	}

    var	axisy = ["Swim","Bike","Run","Cross-Training"],
        table = $("#stravachart"),
        txt = {"font": '10px Fontin-Sans, Arial', stroke: "none", fill: "#FFFFFF", weight: "bold"},
        color = "#3A3A3A",
        strokeColor = "#FFFFFF",
        activityColors = ["#C2453B", "#FFE645", "#ECC66E", "#B25641"],
        height = 300,
        leftgutter = 74,
        topgutter = 20,
        X = (width - leftgutter) / axisx.length,
        Y = (height - topgutter) / axisy.length,
        max = Math.round(Math.min(X / 2 - 5, Y / 2 - 5)),
        min = 10;
        
	// Clear canvas
	stravaChartR.clear();

	// Canvas background
    stravaChartR.rect(0, 0, width, height, 10).attr({fill: color});

	// X-axis labels (days of the week for desktop or total for mobile)
    for (var i = 0, ii = axisx.length; i < ii; i++) {
        stravaChartR.text(leftgutter + X * (i + .5), 6, axisx[i]).attr(txt);
    }
    
    // Y-axis labels (activity types)
    for (var i = 0, ii = axisy.length; i < ii; i++) {
        stravaChartR.text(40, Y * (i + .5) + topgutter, axisy[i]).attr(txt);
    }
    
    // Get max activity duration and total duration/distance by activity type
    var	maxDuration = 0;
    
    var totalsByActivity = [];
    for (var i = 0; i < axisy.length; i++) {
    	totalsByActivity[i] = {"duration": 0, "distance": 0.0};
    }

	$(dataJson).each(function(i, val) {
		maxDuration = Math.max(maxDuration, val.duration);
		
		var i = axisy.indexOf(val.type);
		totalsByActivity[i].duration += val.duration;
		totalsByActivity[i].distance += val.distance;
	});
	
	// Find max duration across activity types (for mobile)
	if (mobile) {
		maxDuration = 0;
		for (var i = 0; i < totalsByActivity.length; i++) {
    		maxDuration = Math.max(maxDuration, totalsByActivity[i].duration);
    	}
	}
	
    // Data points (activities)
    if (mobile) {
    	$.each(totalsByActivity, function(i, val) {
			var	col = 0,
				row = i,
				R = Math.max(Math.min(Math.round(val.duration / maxDuration * 100), max), min),
				activityLabel = stravaChartR.text(leftgutter + X * (col + .5), Y * (row + .5) + topgutter, formatDuration(val.duration) + ", " + val.distance + " mi.").attr(txt).hide(),
				activity = stravaChartR.ellipse(leftgutter + X * (col + .5), Y * (row + .5) + topgutter, R, R).attr({stroke: strokeColor, "stroke-dasharray": ". ", fill: activityColors[row]}),
				attrs = [{x: leftgutter + X * (col + .5), y: Y * (row + .5) + topgutter, rx: R + 20, ry: R + 5, stroke: strokeColor, "stroke-dasharray": ". ", fill: activityColors[row], opacity: 0.1},
					{x: leftgutter + X * (col + .5), y: Y * (row + .5) + topgutter, rx: R, ry: R, stroke: strokeColor, "stroke-dasharray": ". ", fill: activityColors[row], opacity: 1.0}];

			activity[0].onmouseover = function() {
				activityLabel.show();
				activity.stop().animate(attrs[0], 200);
			};

			activity[0].onmouseout = function() {
				activityLabel.hide();
				activity.stop().animate(attrs[1], 200);
			};
		});
    } else {
		$(dataJson).each(function(i, val) {
			var	col = axisx.indexOf(val.day),
				row = axisy.indexOf(val.type),
				R = Math.max(Math.min(Math.round(val.duration / maxDuration * 100), max), min),
				activityLabel = stravaChartR.text(leftgutter + X * (col + .5), Y * (row + .5) + topgutter, formatDuration(val.duration) + ", " + val.distance + " mi.").attr(txt).hide(),
				activity = stravaChartR.ellipse(leftgutter + X * (col + .5), Y * (row + .5) + topgutter, R, R).attr({stroke: strokeColor, "stroke-dasharray": ". ", fill: activityColors[row]}),
				attrs = [{x: leftgutter + X * (col + .5), y: Y * (row + .5) + topgutter, rx: R + 20, ry: R + 5, stroke: strokeColor, "stroke-dasharray": ". ", fill: activityColors[row], opacity: 0.1},
					{x: leftgutter + X * (col + .5), y: Y * (row + .5) + topgutter, rx: R, ry: R, stroke: strokeColor, "stroke-dasharray": ". ", fill: activityColors[row], opacity: 1.0}];

			activity[0].onmouseover = function() {
				activityLabel.show();
				activity.stop().animate(attrs[0], 200);
			};

			activity[0].onmouseout = function() {
				activityLabel.hide();
				activity.stop().animate(attrs[1], 200);
			};
		});
	}
};

/**
 * formatDuration converts a number of seconds to HH:MM format.
 * @param duration - number of seconds
 * @return newDuration - duration converted to HH:MM format
 */
function formatDuration(duration) {
	var	hours = parseInt(duration / 3600) % 24,
		minutes = parseInt(duration / 60) % 60,
		//newDuration = (hours < 10 ? "0" + hours : hours) + ":" + (minutes < 10 ? "0" + minutes : minutes);
		newDuration = (hours > 0 ? hours + " h " : "") + (minutes < 10 ? "0" + minutes  + " min" : minutes + " min");
		
	return newDuration;
} 

$(document).ready(function() {
	stravaChartR = Raphael("stravachartsvg", 600, 300);
	fetchActivities();
});

$(window).resize(function() {
	if ((currentWindowWidth > 767 && $(window).width() <= 767)
		|| (currentWindowWidth <= 767 && $(window).width() > 767)) {
		currentWindowWidth = $(window).width()
		
		drawStravaChart();
	}
});

/**
 * fetchNextWeek gets the activities for the next week
 */
function fetchNextWeek() {
	fetchActivities(nextWeek);
}

/**
 * fetchPrevWeek gets the activities for the last week
 */
function fetchPrevWeek() {
	fetchActivities(prevWeek);
}

/**
 * fetchActivities makes an AJAX call to the StravaChart RESTful
 * web service to retrieve activities for a given week.
 *
 * @param startInterval int The beginning day of the week in YYYYMMDD format
 */
function fetchActivities(startInterval) {	
	$.ajax({
		type: "GET",
		url: "StravaChart/index.php",
		dataType: "json",
		data: { "startInterval": startInterval },
		success: function(resp) {
			dataJson = resp["data"];
			curWeek = resp["curWeek"];
			$("#stravachartheader h3").html("<a href=\"javascript:fetchPrevWeek()\">&lt;&lt;</a> Activity for week of " + curWeek + " <a href=\"javascript:fetchNextWeek()\">&gt;&gt;</a>");
			nextWeek = resp["nextWeek"];
			prevWeek = resp["prevWeek"];
			drawStravaChart();
		},
		error: function(e) {
		}
	});
}