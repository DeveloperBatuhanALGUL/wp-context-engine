<?php

use PHPUnit\Framework\TestCase;

class ContentIndexerTest extends TestCase {

    public function test_chunk_basic(): void {
        $method = new ReflectionMethod( WPCE_ContentIndexer::class, 'chunk' );
        $method->setAccessible( true );

        $text   = implode( ' ', array_fill( 0, 100, 'word' ) );
        $chunks = $method->invoke( null, $text );

        $this->assertNotEmpty( $chunks );
        $this->assertArrayHasKey( 'content', $chunks[0] );
        $this->assertArrayHasKey( 'token_count', $chunks[0] );
    }

    public function test_chunk_empty_string(): void {
        $method = new ReflectionMethod( WPCE_ContentIndexer::class, 'chunk' );
        $method->setAccessible( true );

        $chunks = $method->invoke( null, '' );

        $this->assertSame( [], $chunks );
    }

    public function test_chunk_respects_max_chars(): void {
        $method = new ReflectionMethod( WPCE_ContentIndexer::class, 'chunk' );
        $method->setAccessible( true );

        $text   = implode( ' ', array_fill( 0, 700, 'word' ) );
        $chunks = $method->invoke( null, $text );

        foreach ( $chunks as $chunk ) {
            $this->assertLessThanOrEqual(
                WPCE_ContentIndexer::CHUNK_SIZE,
                $chunk['token_count']
            );
        }
    }

    public function test_chunk_overlap(): void {
        $method = new ReflectionMethod( WPCE_ContentIndexer::class, 'chunk' );
        $method->setAccessible( true );

        $words = array_map( fn( $i ) => "word{$i}", range( 1, 700 ) );
        $text  = implode( ' ', $words );

        $chunks = $method->invoke( null, $text );

        $this->assertGreaterThan( 1, count( $chunks ) );

        $first_end   = array_slice( explode( ' ', $chunks[0]['content'] ), -WPCE_ContentIndexer::CHUNK_OVERLAP );
        $second_start = array_slice( explode( ' ', $chunks[1]['content'] ), 0, WPCE_ContentIndexer::CHUNK_OVERLAP );

        $this->assertSame( $first_end, $second_start );
    }

    public function test_chunk_single_word(): void {
        $method = new ReflectionMethod( WPCE_ContentIndexer::class, 'chunk' );
        $method->setAccessible( true );

        $chunks = $method->invoke( null, 'hello' );

        $this->assertCount( 1, $chunks );
        $this->assertSame( 'hello', $chunks[0]['content'] );
        $this->assertSame( 1, $chunks[0]['token_count'] );
    }

    public function test_indexable_post_types_default(): void {
        $types = WPCE_ContentIndexer::indexable_post_types();

        $this->assertContains( 'post', $types );
        $this->assertContains( 'page', $types );
    }
}
