<?php
/** Standard WooCommerce emails for collection and schedule confirmations. @package Patsch9Orderdate */
namespace Patsch9\Orderdate;
defined( 'ABSPATH' ) || exit;

final class PickupEmail extends \WC_Email {
	public string $pickup_event;
	public string $confirmation_url = '';

	public function __construct( string $event ) {
		$this->pickup_event = $event;
		$this->id = 'p9od_' . $event;
		$this->customer_email = 'confirmed_admin' !== $event;
		$this->title = $this->event_title();
		$this->description = __( 'Abholbenachrichtigung mit Bestellpositionen, Terminen und Abholort.', 'patsch9-orderdate' );
		$this->template_html = 'emails/pickup.php';
		$this->template_plain = 'emails/plain/pickup.php';
		$this->template_base = dirname( P9OD_FILE ) . '/templates/';
		$this->placeholders = array( '{order_number}' => '', '{order_date}' => '' );
		if ( 'rescheduled' !== $event ) {
			$action = match ( $event ) {
				'ready' => 'woocommerce_order_status_' . PickupManager::READY,
				'collected' => 'woocommerce_order_status_' . PickupManager::COLLECTED,
				default => 'p9od_pickup_confirmed',
			};
			add_action( $action . '_notification', array( $this, 'trigger' ), 10, 1 );
		}
		parent::__construct();
		if ( ! $this->customer_email ) {
			$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
		}
	}

	private function event_title(): string {
		return match ( $this->pickup_event ) {
			'ready' => __( 'Bereit zur Abholung', 'patsch9-orderdate' ),
			'collected' => __( 'Bestellung abgeholt', 'patsch9-orderdate' ),
			'rescheduled' => __( 'Neuen Abholtermin bestätigen', 'patsch9-orderdate' ),
			'confirmed_admin' => __( 'Abholtermin vom Kunden bestätigt', 'patsch9-orderdate' ),
			default => __( 'Abholtermin bestätigt', 'patsch9-orderdate' ),
		};
	}

	public function get_default_subject() {
		/* translators: %s: Email event title; {order_number}: WooCommerce placeholder. */
		return sprintf( __( '%s – Bestellung #{order_number}', 'patsch9-orderdate' ), $this->event_title() );
	}
	public function get_default_heading() {
		return $this->event_title();
	}

	public function trigger( $order_id, $token = '' ): bool {
		$this->setup_locale();
		try {
			$this->object = wc_get_order( $order_id );
			$this->confirmation_url = '';
			if ( ! $this->object || ! PickupManager::managed( $this->object ) ) {
				return false;
			}
			if ( 'ready' === $this->pickup_event && ! $this->object->has_status( PickupManager::READY ) ) {
				return false;
			}
			if ( 'collected' === $this->pickup_event && ! $this->object->has_status( PickupManager::COLLECTED ) ) {
				return false;
			}
			if ( 'rescheduled' === $this->pickup_event ) {
				if ( ! is_string( $token ) || ! PickupManager::valid_token( $this->object, $token ) ) {
					return false;
				}
				$this->confirmation_url = add_query_arg( array( 'p9od_confirm' => '1', 'order' => $this->object->get_id(), 'token' => $token ), home_url( '/' ) );
			}
			if ( $this->customer_email ) {
				$this->recipient = $this->object->get_billing_email();
			}
			$this->placeholders['{order_number}'] = $this->object->get_order_number();
			$this->placeholders['{order_date}'] = $this->object->get_date_created() ? wc_format_datetime( $this->object->get_date_created() ) : '';
			if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
				return false;
			}
			$sent = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			$this->object->add_order_note( $sent ? $this->event_title() . ': ' . __( 'E-Mail versendet.', 'patsch9-orderdate' ) : $this->event_title() . ': ' . __( 'E-Mail-Versand fehlgeschlagen.', 'patsch9-orderdate' ) );
			return $sent;
		} finally {
			$this->confirmation_url = '';
			$this->restore_locale();
		}
	}

	public function message(): string {
		return match ( $this->pickup_event ) {
			'ready' => __( 'Deine Bestellung ist bereit zur Abholung. Bitte beachte die vereinbarten Abholtermine und den Abholort.', 'patsch9-orderdate' ),
			'collected' => __( 'Deine Bestellung wurde abgeholt. Vielen Dank für deinen Einkauf!', 'patsch9-orderdate' ),
			'rescheduled' => __( 'Wir schlagen für die unten genannte Position einen neuen Abholtermin vor. Bis zu deiner Bestätigung bleibt der bisherige Termin gültig. Der Link gilt sieben Tage. Bei Rückfragen kontaktiere uns bitte.', 'patsch9-orderdate' ),
			'confirmed_admin' => __( 'Der Kunde hat die Terminänderung bestätigt. Die aktualisierten Abholtermine stehen unten.', 'patsch9-orderdate' ),
			default => __( 'Deine Terminänderung ist bestätigt. Die aktualisierten Abholtermine stehen unten.', 'patsch9-orderdate' ),
		};
	}

	private function template_args( bool $plain ): array {
		return array( 'order' => $this->object, 'email_heading' => $this->get_heading(), 'sent_to_admin' => ! $this->customer_email, 'plain_text' => $plain, 'email' => $this, 'additional_content' => $this->get_additional_content() );
	}
	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->template_args( false ), '', $this->template_base );
	}
	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->template_args( true ), '', $this->template_base );
	}
}
