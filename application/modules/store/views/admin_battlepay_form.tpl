<div class="row">
    <div class="tab-content border-muted-200 dark:border-muted-700 dark:bg-muted-800 relative w-full border bg-white transition-all duration-300 rounded-xl p-6">

        <form id="BattlePayForm" action="{$action}" method="post" onSubmit="return BattlePay.submit(this);">

            <div class="mb-3">
                <label class="form-label" for="bp_name">{lang('name', 'store')}</label>
                <input class="form-control" type="text" name="name" id="bp_name" maxlength="120" value="{if $item}{$item.name}{/if}" />
            </div>

            <div class="mb-3">
                <label class="form-label" for="bp_description">{lang('description', 'store')}</label>
                <textarea class="form-control" name="description" id="bp_description" rows="3">{if $item}{$item.description}{/if}</textarea>
            </div>

            <div class="mb-3">
                <label class="form-label" for="bp_category">{lang('battlepay_category', 'store')}</label>
                <input class="form-control" type="text" name="category" id="bp_category" list="bp_categories" maxlength="40" value="{if $item}{$item.category}{/if}" />
                <datalist id="bp_categories">
                    {foreach from=$categories item=cat}
                        <option value="{$cat}"></option>
                    {/foreach}
                </datalist>
                <small class="text-muted">{lang('battlepay_category_hint', 'store')}</small>
            </div>

            <div class="mb-3">
                <label class="form-label" for="bp_icon">{lang('battlepay_icon', 'store')}</label>
                <div class="d-flex align-items-center" style="gap:10px;">
                    <img id="bp_icon_preview" src="{if $item && $item.icon}{if $item.icon|substr:0:4 == 'http'}{$item.icon}{else}{$CI->config->item('api_item_icons')}/small/{$item.icon}.jpg{/if}{else}{$CI->config->item('api_item_icons')}/small/inv_misc_gift_02.jpg{/if}" width="40" height="40" style="border-radius:6px;" onerror="this.style.opacity=0.3;" />
                    <input class="form-control" type="text" name="icon" id="bp_icon" data-iconbase="{$CI->config->item('api_item_icons')}" placeholder="inv_misc_gift_02" value="{if $item}{$item.icon}{/if}" />
                </div>
                <small class="text-muted">{lang('battlepay_icon_hint', 'store')}</small>
            </div>

            <div class="mb-3">
                <label class="form-label" for="bp_price">{lang('battlepay_price', 'store')} (&euro;)</label>
                <input class="form-control" type="number" min="0" name="price" id="bp_price" value="{if $item}{$item.price}{else}0{/if}" />
            </div>

            <div class="mb-3">
                <label class="form-label" for="bp_delivery">{lang('battlepay_delivery', 'store')}</label>
                <textarea class="form-control" name="delivery_command" id="bp_delivery" rows="2" placeholder="send items $character &quot;Tienda&quot; &quot;Gracias por tu compra&quot; 49426:1">{if $item}{$item.delivery_command}{/if}</textarea>
                <small class="text-muted">{lang('battlepay_delivery_hint', 'store')}</small>
            </div>

            <div class="mb-3 form-check form-switch">
                <input class="form-check-input" type="checkbox" name="enabled" id="bp_enabled" value="1" {if !$item || $item.enabled}checked{/if} />
                <label class="form-check-label" for="bp_enabled">{lang('battlepay_enabled', 'store')}</label>
            </div>

            <div class="form-group mt-4">
                <button type="submit" class="btn btn-primary">{lang('save', 'store')}</button>
                <a href="{$url}store/admin_battlepay" class="btn btn-secondary">{lang('cancel', 'store')}</a>
            </div>

        </form>

    </div>
</div>
