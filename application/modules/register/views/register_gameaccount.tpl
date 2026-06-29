<div class="page-subbody mt-0">
    <div class="col-12 col-xxl-6 col-xl-6 col-lg-6 col-md-12 col-sm-12 mx-auto">
        <div class="card-body p-5">
            <p class="mb-4">{lang('gameaccount_intro', 'register')}</p>

            <div class="mb-3">
                <label class="form-label small mb-1">{lang('gameaccount_bnet', 'register')}</label>
                <input class="form-control" type="text" value="{$email}" disabled />
            </div>

            <div class="mb-3">
                <label class="form-label small mb-1">{lang('gameaccount_new', 'register')}</label>
                <input class="form-control" type="text" value="{$next_label}" disabled />
            </div>

            {form_open('register')}
                <div class="form-group text-center mt-4">
                    <button class="card-footer nice_button" type="submit" name="create_game_account">{lang('gameaccount_button', 'register')}</button>
                </div>
            {form_close()}
        </div>
    </div>
</div>
