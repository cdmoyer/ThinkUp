{$apptitle} Daily Email Digest
Your insights from the past day:

{foreach from=$insights item=insight}
{if $insight->text ne ''}
* {$insight->prefix} {$insight->text}
{/if}
{/foreach}

Sent to you by {$apptitle}. 
Change your mail preferences here {$application_url}account/index.php?m=manage#instances
