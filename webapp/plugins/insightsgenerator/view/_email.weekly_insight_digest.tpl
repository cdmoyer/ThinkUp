{$apptitle} Weekly Email Digest
Your insights from the past week:

{foreach from=$insights item=insight}
{if $insight->text ne ''}
* {$insight->text}
{/if}
{/foreach}

Sent to you by {$apptitle}. 
Change your mail preferences here {$site_root_path}account/index.php?m=manage#instances
