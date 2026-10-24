<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../includes/class-thrg-stk-access.php';

$A = 'THRG_STK_Access';
$reader  = array( 'read' => true, 'create' => false, 'manage' => false, 'see_private' => false );
$writer  = array( 'read' => true, 'create' => true, 'manage' => false, 'see_private' => false );
$manager = array( 'read' => true, 'create' => true, 'manage' => true, 'see_private' => false );
$mgr_pv  = array( 'read' => true, 'create' => true, 'manage' => true, 'see_private' => true );
$none    = array( 'read' => false, 'create' => false, 'manage' => false, 'see_private' => false );

$team      = array( 'author_id' => 1, 'visibility' => 'team', 'mentions' => array( 4 ) );
$mentioned = array( 'author_id' => 1, 'visibility' => 'mentioned', 'mentions' => array( 4 ) );
$private   = array( 'author_id' => 1, 'visibility' => 'private', 'mentions' => array( 4 ) );

check( 'logged out sees nothing', ! $A::can_view( $team, 0, $writer ) );
check( 'no read cap sees nothing', ! $A::can_view( $team, 4, $none ) );
check( 'author without read cap sees nothing', ! $A::can_view( $team, 1, $none ) );
check( 'reader sees team note', $A::can_view( $team, 5, $reader ) );
check( 'reader not mentioned cannot see mentioned-only', ! $A::can_view( $mentioned, 5, $reader ) );
check( 'mentioned reader sees mentioned-only', $A::can_view( $mentioned, 4, $reader ) );
check( 'manager sees mentioned-only', $A::can_view( $mentioned, 9, $manager ) );
check( 'author sees private', $A::can_view( $private, 1, $writer ) );
check( 'mentioned person cannot see private', ! $A::can_view( $private, 4, $writer ) );
check( 'manager cannot see private by default', ! $A::can_view( $private, 9, $manager ) );
check( 'manager with setting sees private', $A::can_view( $private, 9, $mgr_pv ) );

check( 'author with create can edit', $A::can_edit( $team, 1, $writer ) );
check( 'author without create cannot edit', ! $A::can_edit( $team, 1, $reader ) );
check( 'other writer cannot edit', ! $A::can_edit( $team, 5, $writer ) );
check( 'manager can edit team note', $A::can_edit( $team, 9, $manager ) );
check( 'manager cannot edit private without setting', ! $A::can_edit( $private, 9, $manager ) );
check( 'mentioned reader can resolve', $A::can_resolve( $team, 4, $reader ) );
check( 'unmentioned reader cannot resolve', ! $A::can_resolve( $team, 5, $reader ) );
check( 'mentioned cannot resolve private they cannot see', ! $A::can_resolve( $private, 4, $writer ) );
check( 'delete follows edit', $A::can_delete( $team, 1, $writer ) && ! $A::can_delete( $team, 4, $reader ) );
check( 'string ids compare', $A::can_edit( array( 'author_id' => '1', 'visibility' => 'team', 'mentions' => array( '4' ) ), 1, $writer ) && $A::is_mentioned( array( 'mentions' => array( '4' ) ), 4 ) );

done();
