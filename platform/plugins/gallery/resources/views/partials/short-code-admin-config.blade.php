<div class="form-group">
    <label class="control-label">{{ trans('plugins/gallery::gallery.shortcode_name') }}</label>
    {!! Form::input('text', 'limit', Arr::get($attributes, 'limit', 8), ['class' => 'form-control', 'placeholder' => trans('plugins/gallery::gallery.limit_display')]) !!}
</div>
