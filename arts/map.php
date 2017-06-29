<?php
require_once("configuration.php");
require_once("headandfoot.php");
print_header();
?>

<div id="map" style="width: 100%; height: 500px; padding:0px; margin:0px;">
    <span id="maploading">Loading Map</span> ----
</div>

<div style="width:720px; position:relative;">
    <table width="100%" border="0">
        <tr>
            <td>
                <table>
                    <tr>
                        <td><img src="map/green.png" width="32" height="32"/></td>
                        <td>Service by MPS licensed art and/or music specialist and community arts partners</td>
                    </tr>
                </table>
            </td>

            <td>
                <table>
                    <tr>
                        <td><img src="map/orange.png" width="32" height="32"/></td>
                        <td>Service by community arts partners</td>
                    </tr>
                </table>
            </td>

        </tr>
        <tr>
            <td>
                <table>
                    <tr>
                        <td><img src="map/yellow.png" width="32" height="32"/></td>
                        <td>Service by MPS licensed art and/or music specialists</td>
                    </tr>
                </table>
            </td>
            <td>
                <table>
                    <tr>
                        <td><img src="map/red.png" width="32" height="32"/></td>
                        <td>No Service (except field trips and performances/assemblies)</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>

<script src="scripts/jquery.js"></script>
<script src="map/infobubble.js"></script>
<script src="map/map.js"></script>
<script async defer
        src="https://maps.googleapis.com/maps/api/js?key=AIzaSyA-PTfbQyvLk_aDsaRk9FgmkE4IpzSoD0U&callback=initMap">
</script>

<?php print_footer(); ?>  