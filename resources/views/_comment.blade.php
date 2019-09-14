@inject('since', 'App\Service\SinceService')
{{--@dump($comment)--}}
[{{ $comment['username'] }}] - {{ $since->since((int)$comment['timestamp']) }} ({{ $comment['likes'] ?? 0 }}) <br/>
{!! nl2br($comment['comment']) !!}<br/>
<button class="like-btn btn btn-primary" data-id="{{ $comment['id'] }}">
    <i class="fa fa-heart"></i>
    <span class="sr-only">Like</span>
</button>
@if ($username === $comment['username'])
<button class="delete-btn btn btn-danger" data-id="{{ $comment['id'] }}">
    <i class="fa fa-trash"></i>
    <span class="sr-only">Delete</span>
</button>
@endif
<hr/>
