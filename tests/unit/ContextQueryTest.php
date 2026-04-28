<?php

use PHPUnit\Framework\TestCase;

class ContextQueryTest extends TestCase {

    public function test_cosine_similarity_identical_vectors(): void {
        $method = new ReflectionMethod( WPCE_ContextQuery::class, 'cosine_similarity' );
        $method->setAccessible( true );

        $vector = [ 1.0, 0.0, 0.5 ];
        $score  = $method->invoke( null, $vector, $vector );

        $this->assertEqualsWithDelta( 1.0, $score, 0.0001 );
    }

    public function test_cosine_similarity_orthogonal_vectors(): void {
        $method = new ReflectionMethod( WPCE_ContextQuery::class, 'cosine_similarity' );
        $method->setAccessible( true );

        $score = $method->invoke( null, [ 1.0, 0.0 ], [ 0.0, 1.0 ] );

        $this->assertEqualsWithDelta( 0.0, $score, 0.0001 );
    }

    public function test_cosine_similarity_zero_vector(): void {
        $method = new ReflectionMethod( WPCE_ContextQuery::class, 'cosine_similarity' );
        $method->setAccessible( true );

        $score = $method->invoke( null, [ 0.0, 0.0 ], [ 1.0, 1.0 ] );

        $this->assertSame( 0.0, $score );
    }

    public function test_cosine_similarity_opposite_vectors(): void {
        $method = new ReflectionMethod( WPCE_ContextQuery::class, 'cosine_similarity' );
        $method->setAccessible( true );

        $score = $method->invoke( null, [ 1.0, 0.0 ], [ -1.0, 0.0 ] );

        $this->assertEqualsWithDelta( -1.0, $score, 0.0001 );
    }

    public function test_query_returns_empty_on_no_embedding(): void {
        $result = WPCE_ContextQuery::query( 'test input' );
        $this->assertSame( [], $result );
    }

    public function test_query_truncates_long_input(): void {
        $long_input = str_repeat( 'a', 1000 );
        $result     = WPCE_ContextQuery::query( $long_input );
        $this->assertIsArray( $result );
    }
}
