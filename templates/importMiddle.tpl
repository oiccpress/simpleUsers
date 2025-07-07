<form id="importMXmlForm" class="pkp_form" action="{plugin_url path="import"}" method="post" target="_blank">
    {csrf}

    {* Container for uploaded file *}
    <input type="hidden" name="temporaryFileId" id="temporaryFileId" value="{$temporaryFileId}" />
    
    {fbvFormArea id="importForm"}

        <table class="form">
            {foreach from=$xmlData item=item}
                <tr>
                    <td>{$item['key']}</td>
                    <td>
                        <select name="xml[{$item['key']}]" class="form-control">
                            {foreach from=$item['options'] key=$key item=$opt}
                                <option value="{$key}">{$opt}</option>
                            {/foreach}
                        </select>
                    </td>
                </tr>
            {/foreach}
        </table>

    {/fbvFormArea}

    {fbvFormButtons submitText="plugins.importexport.simpleUsers.next" hideCancel="true"}
</form>