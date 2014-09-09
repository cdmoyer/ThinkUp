<div id="geo_analysis_{$i->id}" style="height: 250px; 100%">&nbsp;</div>
<script type="text/javascript">
    // Load the Visualization API and the standard charts
    google.load('visualization', '1', {literal}{ 'packages': ['map'] }{/literal});
    // Set a callback to run when the Google Visualization API is loaded.
    google.setOnLoadCallback(drawChart{$i->id} );
    {literal}
    function drawChart{/literal}{$i->id}{literal}() {
    {/literal}
       var geo_analysis_data_{$i->id} = new google.visualization.arrayToDataTable([
            ['City', 'User']
        {foreach from=$i->related_data.geo_data item=geo}
            , ['{$geo.city}','{$geo.name}']
        {/foreach}
        {literal}
         ]);
        var c = window.tu.constants.colors;
        var geo_analysis_chart_{/literal}{$i->id}{literal} = new google.visualization.ChartWrapper({
            containerId: 'geo_analysis_{/literal}{$i->id}{literal}',
            chartType: 'Map',
            colors: {/literal}[c.{$color}, c.{$color}_dark, c.{$color}_darker],{literal}
            dataTable: geo_analysis_data_{/literal}{$i->id}{literal},
            'options': { showTip: true }
        });
        geo_analysis_chart_{/literal}{$i->id}{literal}.draw();
        {/literal}{include file=$tpl_path|cat:"_chartcallback.tpl"}{literal}
    }
    {/literal}
</script>
