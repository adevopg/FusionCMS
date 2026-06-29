<div class="row">
    <div class="tab-content border-muted-200 dark:border-muted-700 dark:bg-muted-800 relative w-full border bg-white transition-all duration-300 rounded-xl p-6">

        <div class="btn-toolbar justify-content-between mb-3">
            <div class="input-group group/nui-input relative">
                <input type="text" id="BattlePaySearch" class="form-control" placeholder="{lang('search', 'store')}">
            </div>
            {if hasPermission("canAddItems")}
                <span class="pull-right">
                    <a class="relative font-sans font-normal text-sm inline-flex items-center justify-center leading-5 no-underline h-8 px-3 py-2 space-x-1 border nui-focus transition-all duration-300 text-muted-700 border-muted-300 dark:text-white dark:bg-muted-700 dark:border-muted-600 hover:enabled:bg-muted-50 rounded-md" href="{$url}store/admin_battlepay/add">{lang('battlepay_add', 'store')}</a>
                </span>
            {/if}
        </div>

        {if $items}
            <table class="table table-responsive-md table-hover">
                <thead>
                <tr>
                    <th>#</th>
                    <th>{lang('battlepay_icon', 'store')}</th>
                    <th>{lang('name', 'store')}</th>
                    <th>{lang('description', 'store')}</th>
                    <th>{lang('battlepay_category', 'store')}</th>
                    <th>{lang('battlepay_price', 'store')}</th>
                    <th style="text-align:center;">{lang('battlepay_enabled', 'store')}</th>
                    <th style="text-align:center;">{lang('actions', 'store')}</th>
                </tr>
                </thead>
                <tbody id="BattlePayTableResult">
                {foreach from=$items item=item}
                    <tr>
                        <td>{$item.id}</td>
                        <td><img src="{if $item.icon && $item.icon|substr:0:4 == 'http'}{$item.icon}{elseif $item.icon}{$CI->config->item('api_item_icons')}/small/{$item.icon}.jpg{else}{$CI->config->item('api_item_icons')}/small/inv_misc_gift_02.jpg{/if}" width="32" height="32" style="border-radius:5px;" onerror="this.style.opacity=0.3;" /></td>
                        <td data-bs-toggle="tooltip" data-html="true" title="{$item.name}"><b>{character_limiter($item.name, 34)}</b></td>
                        <td data-bs-toggle="tooltip" data-html="true" title="{$item.description}">{character_limiter($item.description, 28)}</td>
                        <td>{$item.category}</td>
                        <td><b>{$item.price} &euro;</b></td>
                        <td style="text-align:center;">
                            {if hasPermission("canEditItems")}
                                <a href="javascript:void(0)" onClick="BattlePay.toggle({$item.id}, this)" title="{lang('battlepay_toggle', 'store')}">
                                    {if $item.enabled}<span class="badge bg-success">{lang('battlepay_on', 'store')}</span>{else}<span class="badge bg-secondary">{lang('battlepay_off', 'store')}</span>{/if}
                                </a>
                            {else}
                                {if $item.enabled}{lang('battlepay_on', 'store')}{else}{lang('battlepay_off', 'store')}{/if}
                            {/if}
                        </td>
                        <td style="text-align:center;white-space:nowrap;">
                            {if hasPermission("canEditItems")}
                                <a href="{$url}store/admin_battlepay/edit/{$item.id}" class="btn btn-sm btn-primary">{lang('edit', 'store')}</a>
                            {/if}
                            {if hasPermission("canRemoveItems")}
                                <a href="javascript:void(0)" onClick="BattlePay.remove({$item.id}, this)" class="btn btn-sm btn-danger">{lang('delete', 'store')}</a>
                            {/if}
                        </td>
                    </tr>
                {/foreach}
                </tbody>
            </table>
        {else}
            <p>{lang('battlepay_empty', 'store')}</p>
        {/if}

    </div>
</div>
