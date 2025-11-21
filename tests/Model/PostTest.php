<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Model\Post;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PostTest extends TestCase
{
    public function testSlugIsTrimmedDuringConstruction(): void
    {
        $post = new Post('Title', 'Body', '  my-slug  ');

        $this->assertSame('my-slug', $post->getSlug());
        $this->assertSame('Title', $post->getTitle());
        $this->assertSame('Body', $post->getContent());
        $this->assertNull($post->getAuthorId());
    }

    public function testSettingNegativeCommentCountThrows(): void
    {
        $post = new Post('T', 'B', 'slug');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('コメント数は 0 以上で指定してください。');
        $post->setCommentCount(-1);
    }
}
