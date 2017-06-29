<?php
session_start();
require_once("../redirect.php");
require_once('../models/database.php');
require_once('../models/format.php');

// An administrator must be logged in to access this script.
redirect_if(!$_SESSION['administrator_id'] || !$_SESSION['admin_name'] || !$_SESSION['type'], 'login.php');

// All school records are needed to create markers for each school.
$schools = my_query("SELECT * FROM school");

$marker_number = 0;               // ID of the map marker associated with the school.
$text = "{'markers': [\n";        // Text to be written to the file.

while ($school_row = mysql_fetch_assoc($schools)) {
    ++$marker_number;

    $partners_text = "'partners': [";           // String to hold names of community arts partners.
    $services_text = "";                        // String to hold services provided by each community arts partner organization.
    $individual_artists = "";                   // String to hold services provided by each individual community arts partner.
    $organization_partners = "";                // String to hold art organization names.
    $individual_partners = "";                  // String to hold individual artist names.

    // Get all arts partners associated with the school and their provided disciplines.
    $disciplines = my_query("SELECT * FROM discipline WHERE school_id = {$school_row['id']}");

    // Get the school's arts services level icon.
    $school_icon = getSchoolIcon($school_row, $disciplines);

    // Set the school's specific information.
    $text .= setSchoolInfo($school_row, $marker_number, $school_icon);

    // Set the school's arts partners JSON data.
    while ($discipline_row = mysql_fetch_assoc($disciplines)) {
        // The community arts partner associated with the disciplines being provided at the school.
        $artist = my_query("SELECT ind_or_org, first_name, last_name, organization_name FROM artist WHERE artist_id = {$discipline_row['art_partner_id']}");

        if (mysql_num_rows($artist) <= 0) {
            $_SESSION['error_message'] = "Database error! Please email entech@uwm.edu for assistance.";
            redirect('index.php');
        }

        $artist_row = mysql_fetch_assoc($artist);

        // Set the JSON data for arts partners.
        if ($artist_row['ind_or_org'] == 'individual') {
            // Individual arts partners with an affiliated organization should be displayed under that organization.
            if (organization_lists_artist($school_row, $discipline_row)) {
                continue;
            }

            $artist_name = "'{$artist_row['first_name']} {$artist_row['last_name']}";
            $individual_artists .= "{$artist_name}':\n{\n";
            $individual_partners .= $artist_name . "',";
        } else {
            $artist_name = "'{$artist_row['organization_name']}";
            $services_text .= "{$artist_name}':\n{\n";
            $organization_partners .= $artist_name . "',";
        }

        // Set the arts partner's services and disciplines JSON data
        $services = my_query("SELECT * FROM service WHERE discipline_id = {$discipline_row['id']}");
        while ($service_row = mysql_fetch_assoc($services)) {
            // The arts partner's affiliations.
            $affiliations = mysql_query(
                "SELECT * FROM artist_organization_association JOIN 
                artist ON artist.artist_id=artist_organization_association.artist_id 					
                AND artist_organization_association.artist_id = {$discipline_row['art_partner_id']} 					
                AND artist_organization_association.discipline_id = {$discipline_row['id']}"
            );

            if ($artist_row['ind_or_org'] == 'organization') {
                $services_text .= setOrganizationService($service_row, $affiliations);
                $services_text .= setDisciplines($discipline_row, $artist_row['ind_or_org']);
            } else {
                $individual_artists .= setIndividualService($service_row);
                $individual_artists .= setDisciplines($discipline_row, $artist_row['ind_or_org']);
            }
        }
    }

    // Add the arts partner's JSON data to the school's JSON data.
    if (mysql_num_rows($disciplines) > 0) {
        $partners_text .= $organization_partners . $individual_partners;
        $partners_text = trim($partners_text, ',') . "],\n";
        $services_text = trim($services_text, ',') . "\n";
        $individual_artists = trim($individual_artists, ',') . "\n";
        $text .= $partners_text . $services_text . $individual_artists . "},\n";
    } else {
        $text .= "'partners': ['NONE']},\n";
    }
}// end while($school_row = mysql_fetch_assoc($schools))

// Close the array of school json objects.
$text .= "]}\n";

// Write the json data to the file.
$myFile = $_SERVER['DOCUMENT_ROOT'] . "/map/points.json";

$fh = fopen($myFile, 'w') or write_error();
if (!fwrite($fh, $text)) {
    $_SESSION['error_message'] = 'Document error. Please contact ENTECH at entech@uwm.edu.';
    redirect_if(true, 'admin_panel.php');
}
fclose($fh);

$_SESSION['success_message'] = 'Map successfully updated.';
redirect_if(true, 'index.php');

/**
 * Get the icon associated with the provided school's current arts services level.
 *
 * @param array $school_row Database row containing the school's information.
 * @return string $school_icon The icon associated with the provided school's current arts services level.
 */
function getSchoolIcon($school_row, $disciplines)
{
    $school_icon = "map/red.png";               // The school's icon.
    $licensed_specialist_provided = false;      // Boolean used to determine if a licensed art or music specialist is provided at the school.
    $art_partner_provided = false;              // Boolean used to determine if a community art partner is provided at the school.

    // Set whether or not the school has a licensed art or music specialist.
    if ($school_row['art_specialist'] > 0 || $school_row['music_specialist'] > 0) {
        $licensed_specialist_provided = true;
    }

    // Set whether or not the school has community arts partners.
    if (mysql_num_rows($disciplines) > 0) {
        $art_partner_provided = true;
    }

    // Set the school's icon colored based on whether or not licensed art and music specialist, and community arts partners are provided.
    if ($licensed_specialist_provided && $art_partner_provided) {
        $school_icon = "map/green.png";
    } elseif ($art_partner_provided) {
        $school_icon = "map/orange.png";
    } elseif ($licensed_specialist_provided) {
        $school_icon = "map/yellow.png";
    }

    // If the school's icon is changing.
    if ($school_icon != $school_row['icon']) {
        // Update the school's icon in the database.
        $update_school = my_query("Update school set icon='{$school_icon}' WHERE id={$school_row['id']}");
        if (!$update_school) {
            $_SESSION['error_message'] = "Database error! Please email entech@uwm.edu for assistance.";
            redirect_if(true, 'index.php');
        }

        // Archive the school's previous services provided status.
        $school_previous_status = my_query("INSERT into school_previous_status (school_id, date_changed, new_icon) 
											values({$school_row['id']}, '" . date('Y-m-d') . "', '{$school_icon}')");
        if (!$school_previous_status) {
            $_SESSION['error_message'] = "Database error! Please email entech@uwm.edu for assistance.";
            redirect_if(true, 'index.php');
        }
    }

    return $school_icon;
}

/**
 * Set JSON data for the provided school's specific information.
 *
 * @param array $school_row Database row containing the school's information.
 * @param integer $marker_number ID of the map marker associated with the school.
 * @param string $school_icon The icon associated with the provided school's current arts services level.
 * @return string $json JSON data for the provided school's specific information.
 */
function setSchoolInfo($school_row, $marker_number, $school_icon)
{
    $json =
        "{\n
        'marker': '{$marker_number}',\n
        'icon': '{$school_icon}',\n	          
        'point': new GLatLng({$school_row['latitude']}, {$school_row['longitude']}),\n
        'school_name': '" . str_replace("\n", "", trim($school_row['name'])) . "',\n
        'address1': '" . str_replace("\n", "", trim($school_row['address1'])) . "',\n
        'address2': '{$school_row['address2']}',\n
        'city_state': '{$school_row['city_state']}',\n
        'phone': '" . format_phone($school_row['phone']) . "',\n
        'principal': '{$school_row['principal']}',\n
        'grade_levels': '{$school_row['grade_levels']}',\n
        'enrollment': '{$school_row['enrollment']}',\n
        'african_american': '{$school_row['african_american']}',\n
        'asian': '{$school_row['asian']}',\n
        'hispanic': '{$school_row['hispanic']}',\n
        'native_american': '{$school_row['native_american']}',\n
        'white': '{$school_row['white']}',\n
        'reduced_lunch': '{$school_row['free_reduced_lunch']}',\n
        'special_needs': '{$school_row['special_needs']}',\n
        'english_learners': '{$school_row['english_language_learners']}',\n
        'art_specialist': '{$school_row['art_specialist']}',\n
        'music_specialist': '{$school_row['music_specialist']}',\n
        'pe_specialist': '{$school_row['pe_specialist']}',\n";

    return $json;
}

/**
 * Set JSON data for the provided arts partner organization service and the organization's affiliations.
 *
 * @param array $service_row Database row containing one of the arts partner organization's services.
 * @param array $affiliations Database records containing the arts partner organization's affiliations.
 * @return string $services_text JSON data for the provided arts partner organization service.
 */
function setOrganizationService($service_row, $affiliations)
{
    $services_text = '';

    if ($service_row['type'] == 'field_trip' || $service_row['type'] == 'performance_assembly' || $service_row['type'] == 'workshop') {
        // Field trip, performance/assembly, and work shop have the same fields.

        $services_text .=
            "'{$service_row['type']}':{'number':'{$service_row['amount']}',
            'students_served':'{$service_row['students_served']}',
			'teachers_served':'{$service_row['teachers_served']}', 
			'grade_levels':['" . str_replace(", ", "','", $service_row['grade_levels']) . "']," .
            getFreeFee($service_row) . "},\n";
    } else {
        // Residency, class/program, and after school/summer have the same fields.

        $services_text .=
            "'{$service_row['type']}':{'number':'{$service_row['amount']}',
            'students_served':'{$service_row['students_served']}',
			'duration':['" . str_replace(", ", "','", $service_row['duration']) . "'], 
			'grade_levels':['" . str_replace(", ", "','", $service_row['grade_levels']) . "']," .
            getFreeFee($service_row) . "},\n";
    }

    if (mysql_num_rows($affiliations) > 0) {
        $services_text .= "'artist_names':[";
        $artist_id = "'artist_ids':[";

        while ($affiliation_row = mysql_fetch_assoc($affiliations)) {
            $services_text .= "'{$affiliation_row['first_name']}" . ' ' . "{$affiliation_row['last_name']}',";
            $artist_id .= "'{$affiliation_row['art_affiliate_id']}',";
        }

        $services_text .= "],\n" . $artist_id . "],\n";
    } else {
        $services_text .= "'artist_names':'',\n'artist_ids':'',\n";
    }

    return $services_text;
}

/**
 * Set JSON data for the provided arts partner service.
 *
 * @param array $service_row Database row containing one of the arts partner's services.
 * @return string $services_text JSON data for the provided arts partner service.
 */
function setIndividualService($service_row)
{
    $services_text = '';

    if ($service_row['type'] == 'field_trip' || $service_row['type'] == 'performance_assembly' || $service_row['type'] == 'workshop') {
        // Field trip, performance/assembly, and work shop have the same fields

        $services_text .=
            "'{$service_row['type']}':{'number':'{$service_row['amount']}',
            'students_served':'{$service_row['students_served']}',
			'teachers_served':'{$service_row['teachers_served']}', 
			'grade_levels':['" . str_replace(", ", "','", $service_row['grade_levels']) . "']," .
            getFreeFee($service_row) . "},\n";

    } else {
        // Residency, class/program, and after school/summer have the same fields.

        $services_text .=
            "'{$service_row['type']}':{'number':'{$service_row['amount']}',
            'students_served':'{$service_row['students_served']}',
			'duration':['" . str_replace(", ", "','", $service_row['duration']) . "'], 
			'grade_levels':['" . str_replace(", ", "','", $service_row['grade_levels']) . "']," .
            getFreeFee($service_row) . "},\n";
    }

    $services_text .= "'artist_names':'',\n'artist_ids':'',\n";

    return $services_text;
}

/**
 * Set the disciplines provided by an art partner.
 *
 * @param array $discipline_row Database row containing one of the school's disciplines.
 * @param string $art_partner_type The art partner's type (Organization or Individual).
 * @return string $discipline_text JSON data of disciplines provided by the art partner.
 */
function setDisciplines($discipline_row, $art_partner_type)
{
    $discipline_text = "'discipline':{";
    $count = substr_count($discipline_row['disciplines_provided'], ',');
    $disciplines = (explode(', ', $discipline_row['disciplines_provided'], $count + 1));
    for ($i = 0; $i <= $count; ++$i) {
        $discipline_text .= '"' . $disciplines[$i] . '":"' . $disciplines[$i];
        if ($i != $count) {
            $discipline_text .= '",';
        } else {
            $discipline_text .= '"},' . "\n";
        }
    }
    $discipline_text .= "'type':'" . $art_partner_type . "'\n},\n";

    return $discipline_text;
}

/**
 * Determine whether or not the artist is listed under all of their organization affiliates.
 *
 * @param array $school_row Database row containing the school's information.
 * @param array $discipline_row Database row containing the school's arts partners and their provided disciplines.
 * @return bool Whether or not the artist is listed under all of their organization affiliates.
 */
function organization_lists_artist($school_row, $discipline_row)
{
    // Get all of the arts partner's affiliates for the school.
    $artist_affiliations = mysql_query(
        "SELECT * FROM artist_organization_association a, discipline d 
                WHERE a.discipline_id = d.id 
                AND a.artist_id = {$discipline_row['art_partner_id']} 
                AND d.school_id={$school_row['id']}"
    );

    if (mysql_num_rows($artist_affiliations) > 0) {
        // Determine if the arts partner is listed with each of their organizations' affiliations.
        while ($row = mysql_fetch_assoc($artist_affiliations)) {
            $organization_affiliation = mysql_query(
                "SELECT a.id FROM artist_organization_association a, discipline d 
                        WHERE a.discipline_id = d.id 
                        AND a.artist_id = {$row['art_affiliate_id']} 
                        AND a.art_affiliate_id= {$row['artist_id']}
                        AND d.school_id={$school_row['id']}"
            );

            if (mysql_num_rows($organization_affiliation) <= 0) {
                return false;
            }
        }
    }

    return true;
}

/**
 * Calculate free and fee values for the provided service type.
 *
 * @param array $service_row Database row containing a service provided by an arts partner.
 * @return string JSON data for free and fee values for the provided service type.
 */
function getFreeFee($service_row)
{
    if ($service_row['free_fee'] == 'both') {
        $free = 1;
        $fee = 1;
    } elseif ($service_row['free_fee'] == 'free') {
        $free = 1;
        $fee = 0;
    } else {
        $free = 0;
        $fee = 1;
    }

    return "'fee':'{$fee}','free':'{$free}'";
}