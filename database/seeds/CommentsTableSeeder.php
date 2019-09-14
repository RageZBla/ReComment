<?php
declare(strict_types=1);

use App\CommentLike;
use Illuminate\Database\Seeder;

class CommentsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $counter = 1;
        factory(App\Comment::class, 50)->create()->each(function ($comment) use(&$counter) {
            if ($counter++ % 10 === 0) {
                $like = new CommentLike();
                $like->fill([
                    'user_id' => $comment->user_id, // Yeah I know pretty egomaniac to like your own content
                ]);
                $comment->likes()->save($like);
            }
        });
    }
}
