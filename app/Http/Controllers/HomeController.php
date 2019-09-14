<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repository\CommentContract;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * @var CommentContract
     */
    private $commentRepository;

    /**
     * Create a new controller instance.
     *
     * @param CommentContract $commentRepository
     */
    public function __construct(CommentContract $commentRepository)
    {
        $this->commentRepository = $commentRepository;
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request)
    {
        return view('home', ['comments' => $this->commentRepository->fetchHomeComments()]);
    }
}
