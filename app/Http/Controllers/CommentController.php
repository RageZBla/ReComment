<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repository\CommentContract;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * @var CommentContract
     */
    private $commentRepository;

    public function __construct(CommentContract $commentRepository)
    {
        $this->commentRepository = $commentRepository;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validateUserIsLoggedIn($request);
        $this->validate($request, ['comment' => 'required|string']);

        $userId = $request->request->get('_user_id');
        $username = $request->request->get('_username');

        $comment = $request->post('comment');

        $this->commentRepository->post($comment, (int)$userId, $username);

        $request->session()->flash('status', 'Comment posted!');

        return redirect()->route('home');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        $this->validateUserIsLoggedIn($request);
        $this->validate($request, ['comment_id' => 'required|integer']);

        $commentId = (int)$request->post('comment_id');
        $comment = $this->commentRepository->find($commentId);
        $userId = $request->request->get('_user_id');

        if ($userId !== (int)$comment['user_id']) {
            return abort(403);
        }

        $this->commentRepository->delete($commentId);

        $request->session()->flash('status', 'Comment deleted!');

        return redirect()->route('home');
    }

    /**
     * Like comment
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function like(Request $request)
    {
        $this->validateuserisloggedin($request);
        $this->validate($request, ['comment_id' => 'required|integer']);
        $commentId = (int)$request->post('comment_id');

        $userId = $request->request->get('_user_id');

        $this->commentRepository->like($commentId, $userId);

        $request->session()->flash('status', 'Comment liked!');

        return redirect()->route('home');
    }

    public function validateUserIsLoggedIn(Request $request): void
    {
        $userId = $request->request->get('_user_id');
        $username = $request->request->get('_username');

        if ($userId === null || $username === null) {
            abort(401);
        }
    }
}
