/**
 * Setup the map and markers.
 */
function initMap() {    
    var map = new google.maps.Map(document.getElementById('map'), {
        zoom: 11,
        center: {lat: 43.038902, lng: -87.906471}
    });

    var infoBubble;

    // Retrieve the map data.
    $.getJSON("map/points.json", function(markers) {
        // Create the markers on the map along with their content for the info window.
        $.each(markers, function(key, school) {
            var location = school.point.split(', ');
            var latLng = {lat: parseFloat(location[0]), lng: parseFloat(location[1])};
            
            var marker = new google.maps.Marker({                
                position: latLng,
                map: map, 
                title: school.school_name, 
                icon: school.icon
            });

            // Initialize info window tabs.
            infoBubble = new InfoBubble();
            infoBubble.addTab();
            infoBubble.addTab();
            infoBubble.addTab();

            // Marker click event.
            google.maps.event.addListener(marker, 'click', function() {
                // Update the information displayed in the info windows to reflect the clicked school.
                infoBubble.updateTab(0, 'School', school_info(school));
                infoBubble.updateTab(1, 'Demographics', demographics(school));
                infoBubble.updateTab(2, 'Arts Partners', partners(school));

                infoBubble.open(map, marker);
            });
        }); // $.each
    }); // $.getJSON
} // initMap()

/**
 * Display information pertaining to the selected school.
 *
 * @param JSON school Information pertaining to the clicked marker.
 * @return HTML school_content The school's information to display.
 */
function school_info(school) {
    // Format the school's address.
    var address1 = (school.address1) ? school.address1+'<br/>' : "";
    var address2 = (school.address2) ? school.address2+'<br/>' : "";
    var city_state = (school.address1 || school.address1) ? school.city_state+'<br/>' : "";

    // Set up the school's information to display.
    var school_content = 
        '<div class="scrollme">'+                      
            school.school_name+'<br/>'+
            address1+
            address2+
            city_state+
            school.phone+'<br/><br/>'+
            'Principal: '+school.principal+'<br/><br/>'+                     
            'Grade Levels: '+school.grade_levels+'<br/>'+
            'Enrollment: '+school.enrollment+
            '<br/><br/><hr>'+
            '<center>'+
            '<h4>2011/12 Arts Education Service Level</h4>'+
            '<img src="'+school.icon+'"></center>'+
        '</div>';

    return school_content;
}// school_info()

/**
 * Display demographics pertaining to the selected school.
 *
 * @param JSON school Information pertaining to the clicked marker.
 * @return HTML demographics_content The demographic information to display.
 */
function demographics(school) {
    var demographics_content = 
        '<div class="scrollme">'+                       
            'African American: '+school.african_american+
            '<br/>Asian: '+school.asian+
            '<br/>Hispanic: '+school.hispanic+
            '<br/>Native American: '+school.native_american+
            '<br/>White: '+school.white+
            '<hr>'+                 
            'Free Reduced Lunch: '+school.reduced_lunch+
            '<br/>With Special Needs: '+school.special_needs+
            '<br/>English Language Learners: '+school.english_learners+
            '<br/>'+
            '<hr>'+
            'Licensed Art Specialist: '+school.art_specialist+
            '<br/>Licensed Music Specialist: '+school.music_specialist+
            '<br/>Licensed Physical Education Specialist: '+school.pe_specialist+
       '</div>';

    return demographics_content;
}// demographics()

/**
 * Display art partners associated with the selected school.
 *
 * @param JSON input Information pertaining to the clicked marker.
 * @return HTML  The art partner information to display.
 */
function partners(input) {
    var partners = "";                              // String to hold html objects that are being created.
    var display_organization_header = true;         // Boolean to determine of the Art Orgnization(s) header has been displayed.
    var display_individual_header = true;           // Boolean to determine of the Individual Artist(s) header has been displayed.
    var display_hr = false;                         // Boolean to determine if the first organization hr should be displayed.

    // If community art partners were provided.
    if (input.partners[0] != "NONE") {
        for (i in input.partners) {
            var partner_name = input.partners[i].replace("\\'", '');

            if (display_organization_header && input[partner_name]['type'] == 'organization') {
                // Display the Art Organization(s) header.
                display_organization_header = false;
                partners += '<br/><center><b>Art Organization(s)</b></center><hr/>';
            } else if(display_individual_header && input[partner_name]['type'] == 'individual') {
                // Display the Individual Artist(s) header.
                display_individual_header = false;
                partners += '<br/><center><b>Individual Artist(s)</b></center><hr/>';
            }
            
            partners += '<div style="background-color:#e1e1e1;width:100%">'+                            
                        '<a href="#"onClick="show_services(\''+input.partners[i]+'\');">'+input.partners[i].replace('\\', '')+'</a></div>';         
            
            if (input[partner_name]['type'] == 'organization') {
                var artists = "";           // String to hold the artist links.
                var artist_count = 0;       // The number of artists associated with the art partner.
                        
                // Create the artist link.
                for (i in input[partner_name]['artist_names']) {
                    ++artist_count;
                    artists +=  '<li><a href="http://arts/artist_profile.php?artist_id='+input[partner_name]['artist_ids'][i]+'">'+input[partner_name]['artist_names'][i]+'</a></li>';                            
                }   

                if(artist_count != 0) {
                    // Display the artist name as a link to their web page/website.
                    partners += '<center>Artist(s)</center><ul>'+artists+'</ul>';
                    partners += '<hr style=" border-color: #666666;border-style: none none dotted;border-width: 1px;color: #FFFFFF;">';
                } else {
                    partners += '<center><b>No Artists Listed<b></center>';
                    partners += '<hr style=" border-color: #666666;border-style: none none dotted;border-width: 1px;color: #FFFFFF;">';
                }
                
                partners +='</table>';      
            } else {
                partners += '<div style="background-color:#e1e1e1;width:100%"><br/></div>';
            }
        } // for (i in input.partners)
    } else {
        partners += '<br/><center><font color="red">No Community Arts Partners Provided</font></center>';
    }
    
    return '<div id="marker'+input.marker+'" class="scrollme">'+
                '<br/>Click on highlighted Service Provider to view service detail.'+
                '<br/><br/>Click on Artist\'s Name to view Artist\'s Profile/Website.'+
                '<br/>'+partners+
            '</div>';
}// partners()