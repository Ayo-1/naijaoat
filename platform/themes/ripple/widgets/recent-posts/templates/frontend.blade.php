@if (is_plugin_active('blog'))
    @php
        $posts = get_recent_posts($config['number_display']);
    @endphp
    @if ($posts->count())
        @if ($sidebar == 'footer_sidebar')
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="widget widget--transparent widget__footer">
                    @else
                        <div class="widget widget__recent-post">
                            @endif
                            <div class="widget__header">
                                <h3 class="widget__title">{{ $config['name'] }}</h3>
                            </div>
                            <div class="widget__content">
                                <ul @if ($sidebar == 'footer_sidebar') class="list list--light list--fadeIn" @endif>
                                    @foreach ($posts as $post)
                                        <li>
                                            @if ($sidebar == 'footer_sidebar')
                                                <a href="{{ $post->url }}" data-number-line="2">{{ $post->name }}</a>
                                            @else
                                                <article class="post post__widget clearfix">
                                                    <div class="post__thumbnail"><img src="{{ RvMedia::getImageUrl($post->image, 'thumb', false, RvMedia::getDefaultImage()) }}" alt="{{ $post->name }}">
                                                        <a href="{{ $post->url }}" class="post__overlay"></a></div>
                                                    <header class="post__header">
                                                        <h5 class="post__title"><a href="{{ $post->url }}" data-number-line="2">{{ $post->name }}</a></h5>
                                                        <div class="post__meta"><span class="post__created-at">{{ $post->created_at->translatedFormat('M d, Y') }}</span></div>
                                                    </header>
                                                </article>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        @if ($sidebar == 'footer_sidebar')
                </div>
        @endif
    @endif
@endif
