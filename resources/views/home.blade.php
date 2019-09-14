@extends('layouts.app')

@section('content')
{{--    @dump($comments)--}}
{{--    @dump($username)--}}
{{--    @php--}}
{{--    die();--}}
{{--    @endphp--}}
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card mb-2">
                <div class="card-header">Post comment</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($username)
                        <form action="{{ url('post') }}" method="post">
                            @csrf
                            <label for="comment" class="col-md-4 col-form-label text-md-right">{{ __('Do you haz comment?') }}</label>
                            <div class="form-group row">
                                <div class="col-md-6">
                                    <textarea id="comment" class="form-control @error('comment') is-invalid @enderror" name="comment" required autofocus rows="5">{{ old('comment') }}</textarea>

                                    @error('comment')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row mb-0">
                                <div class="col-md-8">
                                    <button type="submit" class="btn btn-danger">
                                        {{ __('Post') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    @else
                        You are not logged in! You need to <a href="{{ url('login') }}">login to post comment.</a>.
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">Comments</div>
                <div class="card-body">
                    @if (count($comments))
                        @each('_comment', $comments, 'comment')
                    @else
                        <img src="https://media.makeameme.org/created/no-comments-needed.jpg" class="align-content-center"/>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('inlinescripts')
    <form id="like-form" style="display: none;" method="post" action="{{ url('like') }}">
        @csrf
        <input name="comment_id" value=""/>
    </form>
    <form id="delete-form" style="display: none;" method="post" action="{{ url('delete') }}">
        @csrf
        <input name="_method" value="delete"/>
        <input name="comment_id" value=""/>
    </form>
    <script>
        window.addEventListener('DOMContentLoaded', (event) => {
            const likeBtns = document.querySelectorAll('.like-btn');
            const deleteBtns = document.querySelectorAll('.delete-btn');
            const likeForm = document.querySelector('#like-form');
            const delForm = document.querySelector('#delete-form');
            likeBtns.forEach(btn => {
                btn.addEventListener('click', e => {
                    e.preventDefault();

                    const commentId = btn.attributes['data-id'].value;
                    likeForm['comment_id'].value = commentId;
                    likeForm.submit();
                });
            });
            deleteBtns.forEach(btn => {
                btn.addEventListener('click', e => {
                    e.preventDefault();
                    const commentId = btn.attributes['data-id'].value;
                    delForm['comment_id'].value = commentId;
                    delForm.submit();
                })
            });
        });
    </script>
@endpush
