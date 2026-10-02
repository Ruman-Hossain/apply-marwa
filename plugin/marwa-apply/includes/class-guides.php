<?php
/**
 * Guides for candidates.
 *
 * @package MarwaApply
 */

namespace MarwaApply;

defined( 'ABSPATH' ) || exit;

/**
 * The portal's own writing — the "Career Resources" of a recruitment agency.
 *
 * A note on what is seeded here. Guidance that is true regardless of which
 * agency a worker uses — what the SSW route requires, what an employer
 * interview is like, how recruitment fraud works — is published. Anything
 * that depends on Marwa's own fees, timelines, partner centres or guarantees
 * is seeded as a DRAFT with the gaps marked, because inventing those would
 * put claims on a licensed agency's website that nobody has verified.
 *
 * So the menu fills up with real content immediately, and the half that needs
 * Marwa's facts waits for Marwa.
 */
final class Guides {

	const CPT       = 'ma_guide';
	const SEED_FLAG = 'ma_guides_seeded';

	/**
	 * Hook up.
	 */
	public static function init() {
		self::register();

		add_shortcode( 'ma_guides', array( __CLASS__, 'index' ) );
		add_action( 'wp_head', array( __CLASS__, 'styles' ), 103 );
	}

	/**
	 * The post type.
	 */
	public static function register() {
		register_post_type(
			self::CPT,
			array(
				'labels'       => array(
					'name'          => __( 'Guides', 'marwa-apply' ),
					'singular_name' => __( 'Guide', 'marwa-apply' ),
					'add_new_item'  => __( 'Add a guide', 'marwa-apply' ),
					'edit_item'     => __( 'Edit guide', 'marwa-apply' ),
				),
				'public'       => true,
				'has_archive'  => false,
				'menu_icon'    => 'dashicons-book-alt',
				'menu_position' => 26,
				'supports'     => array( 'title', 'editor', 'excerpt', 'revisions', 'thumbnail', 'page-attributes' ),
				'rewrite'      => array( 'slug' => 'guides' ),
				'show_in_rest' => true,
			)
		);
	}

	/**
	 * Published guides.
	 *
	 * @param int $limit How many.
	 * @return array
	 */
	public static function all( $limit = 40 ) {
		return get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
			)
		);
	}

	/**
	 * The index page.
	 *
	 * @return string
	 */
	public static function index() {
		$guides = self::all();

		$out = '<div class="mo"><div class="ap-shell" style="max-width:1000px">';

		$out .= '<h1 class="ma-g-h1">' . esc_html__( 'Guides', 'marwa-apply' ) . '</h1>';
		$out .= '<p class="ap-note ma-g-lede">' . esc_html__( 'Plain explanations of the things that decide whether you can work abroad — the documents, the exams, the medical, and how to avoid being cheated. Free to read, and you do not need an account.', 'marwa-apply' ) . '</p>';

		if ( ! $guides ) {
			$out .= '<div class="ap-card"><p>' . esc_html__( 'No guides have been published yet.', 'marwa-apply' ) . '</p></div></div></div>';

			return $out;
		}

		$out .= '<div class="ma-guides">';

		foreach ( $guides as $post ) {
			$excerpt = $post->post_excerpt ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 26 );

			$out .= '<a class="ma-guide" href="' . esc_url( (string) get_permalink( $post->ID ) ) . '">';
			$out .= '<h2>' . esc_html( get_the_title( $post->ID ) ) . '</h2>';
			$out .= '<p>' . esc_html( $excerpt ) . '</p>';
			$out .= '<span>' . esc_html__( 'Read this', 'marwa-apply' ) . '</span></a>';
		}

		return $out . '</div></div></div>';
	}

	/**
	 * Styles.
	 */
	public static function styles() {
		if ( ! is_singular( self::CPT ) && ! is_page() ) {
			return;
		}

		?>
<style id="marwa-apply-guides">
.ma-g-h1{margin:0 0 10px;font-size:32px;line-height:1.15}
.ma-g-lede{max-width:62ch;margin:0 0 26px;font-size:16.5px}
.ma-guides{display:grid;grid-template-columns:repeat(auto-fill,minmax(285px,1fr));gap:14px}
.ma-guide{display:block;padding:20px 22px;border:1px solid var(--line,#E3E0D9);border-radius:12px;background:#fff;text-decoration:none;color:inherit;transition:border-color .15s ease,box-shadow .15s ease}
.ma-guide:hover{border-color:var(--navy,#0B2239);box-shadow:0 2px 14px rgba(11,34,57,.10)}
.ma-guide h2{margin:0 0 7px;font-size:17.5px;line-height:1.35;color:var(--navy,#0B2239)}
.ma-guide p{margin:0 0 12px;font-size:14.5px;line-height:1.6;color:var(--muted,#5B6B7C)}
.ma-guide span{font-size:14px;color:var(--accent,#A93A2C)}
.single-ma_guide .entry-content{max-width:70ch}
.single-ma_guide .entry-content h2{margin-top:34px;font-size:22px;line-height:1.3}
.single-ma_guide .entry-content h3{margin-top:24px;font-size:17px}
.single-ma_guide .entry-content li{margin-bottom:7px;line-height:1.65}
.ma-todo-note{border:1px solid #D8B9B2;border-left:4px solid #A93A2C;background:#FDF6F4;border-radius:9px;padding:14px 16px;margin:18px 0;font-size:14.5px;line-height:1.6;color:#4A2A24}
</style>
		<?php
	}

	/**
	 * Starter guides. Safe to run repeatedly — a guide that already exists is
	 * never overwritten, so anything Marwa edits stays edited.
	 */
	public static function seed() {
		foreach ( self::starters() as $slug => $spec ) {
			if ( get_page_by_path( $slug, OBJECT, self::CPT ) ) {
				continue;
			}

			wp_insert_post(
				array(
					'post_type'    => self::CPT,
					'post_status'  => $spec['publish'] ? 'publish' : 'draft',
					'post_title'   => $spec['title'],
					'post_name'    => $slug,
					'post_excerpt' => $spec['excerpt'],
					'post_content' => $spec['body'],
					'menu_order'   => $spec['order'],
				)
			);
		}

		update_option( self::SEED_FLAG, 1 );
	}

	/**
	 * A marked gap, for anything only Marwa can answer.
	 *
	 * @param string $what What is missing.
	 * @return string
	 */
	private static function gap( $what ) {
		return '<p class="ma-todo-note"><strong>Marwa to confirm:</strong> ' . $what . ' This paragraph is a placeholder and must be replaced or removed before this guide is published.</p>';
	}

	/**
	 * The starter set.
	 *
	 * @return array
	 */
	private static function starters() {
		$g = array();

		// ------------------------------------------------- published: fraud
		$g['avoiding-recruitment-fraud'] = array(
			'order'   => 1,
			'publish' => true,
			'title'   => 'How overseas job fraud works, and how to avoid it',
			'excerpt' => 'The people who take money from workers going abroad use a small number of tricks, and all of them are recognisable once you know the shape.',
			'body'    => '
<p>Most people cheated over an overseas job are not careless. They are cheated by somebody who sounds official, who has an office, and who knows the right words. What follows is how it is actually done.</p>

<h2>The four things a fraudster needs you to believe</h2>

<h3>1. That paying now secures the job</h3>
<p>This is the whole trick. A real vacancy is not held for you because you paid, and no fee moves you up a list. If somebody tells you the job will go to someone else unless you pay today, that urgency is the product. Walk away.</p>

<h3>2. That the licence number on the paper is theirs</h3>
<p>Recruiting licence numbers are public. That means anyone can copy one onto a letterhead, and they do — often the number of a real, respectable agency. A licence number shown to you proves nothing by itself. What proves something is checking that number against the Ministry&#8217;s own register, and confirming the office you are standing in is the office that holds it.</p>

<h3>3. That a visa can be guaranteed</h3>
<p>No agency, in any country, can guarantee a visa. A visa is issued by a government after an employer has made a real offer and the paperwork has been attested. Anybody promising a guaranteed visa for a fee is describing something that does not exist.</p>

<h3>4. That the money should go to a person</h3>
<p>Government fees — passport, medical, BMET registration — are paid by you, directly, to those offices, and you get a receipt with your own name on it. Money asked for in cash, or sent to a personal bKash or Nagad number, or to an individual&#8217;s bank account, is money with no record and no way back.</p>

<h2>What a legitimate process looks like</h2>
<ul>
<li>You can see the employer&#8217;s name and the country before you commit to anything.</li>
<li>Every payment you make has a receipt, in your name, from the office that took it.</li>
<li>Nobody holds your passport. You keep it, except for the short periods it is physically needed for a visa application, and you get it back.</li>
<li>You sign a contract you have read, that states the job, the pay, the hours and the length.</li>
<li>Nobody is in a hurry.</li>
</ul>

<h2>If it has already happened</h2>
<p>Being cheated is not something to be ashamed of, and staying quiet is what lets the same person do it to the next family. Report it. The agency whose name was used will usually help, because a fraudster trading on their licence is a threat to them too.</p>

<p><strong>Marwa Overseas never charges a jobseeker to open an account, to complete a profile, or to apply for a job on this site.</strong> If anybody uses Marwa&#8217;s name to ask you for money, tell Marwa.</p>
',
		);

		// ------------------------------------------- published: Japan / SSW
		$g['working-in-japan-ssw'] = array(
			'order'   => 2,
			'publish' => true,
			'title'   => 'Working in Japan: what Specified Skilled Worker actually requires',
			'excerpt' => 'Japan&#8217;s SSW route is open to Bangladeshi workers in a defined list of fields, and it turns on two exams rather than on who you know.',
			'body'    => '
<p>Specified Skilled Worker — 特定技能, <em>tokutei ginou</em> — is the route most Bangladeshi workers going to Japan will use. It is worth understanding properly, because it is unusually transparent: what it requires is written down, and it is the same for everybody.</p>

<h2>Two exams, not one</h2>
<p>For nearly every field you need both:</p>
<ul>
<li><strong>A Japanese language test.</strong> Either the JFT-Basic (国際交流基金日本語基礎テスト) or JLPT N4. These are equivalent for SSW purposes. You only need one of them.</li>
<li><strong>A skills evaluation test for your field.</strong> This is a practical test of whether you can do the work, set by the Japanese industry body for that sector.</li>
</ul>

<p>Care work is the exception: it requires a third test, the 介護日本語評価試験, the care-specific Japanese examination, on top of the other two.</p>

<h2>What SSW is not</h2>
<p>It is not a student visa, and it is not the old technical intern programme. You go as a worker, you are paid the same rate as a Japanese worker doing the same job, and you have the legal protections that come with that. You can change employer within your field, which the intern route did not allow.</p>

<h2>Nursing is not on this route</h2>
<p>This confuses a lot of people, so it is worth stating plainly. Nursing in Japan is not an SSW field. It runs through a separate Economic Partnership Agreement route, and Bangladesh does not currently have one with Japan. Care work (介護) is a different occupation from nursing, and it <em>is</em> open.</p>

<h2>The order to do things in</h2>
<ol>
<li>Decide the field. The skills test is field-specific, so this comes first.</li>
<li>Start the Japanese language study. It is the slowest part and the one people underestimate.</li>
<li>Sit JFT-Basic or JLPT N4.</li>
<li>Sit the skills evaluation test for your field.</li>
<li>Apply to vacancies with both certificates in hand.</li>
</ol>

<p>You can apply through Marwa before you have the certificates — your file records what you have and what you are studying for, and it means you are already on record when a vacancy opens in your field.</p>
',
		);

		// ----------------------------------------- published: interview tips
		$g['interview-with-an-overseas-employer'] = array(
			'order'   => 3,
			'publish' => true,
			'title'   => 'The interview with an overseas employer',
			'excerpt' => 'What these interviews are actually testing, which is usually not what people prepare for.',
			'body'    => '
<p>An interview for overseas work is usually short, often on a video call, and sometimes through an interpreter. It is not a conversation about your ambitions. It is an employer deciding three things.</p>

<h2>The three things they are deciding</h2>
<ol>
<li><strong>Can you do the work?</strong> Expect specific, practical questions. Not "are you a hard worker" but "what thickness of plate have you welded" or "how many residents did you assist in a shift".</li>
<li><strong>Will you cope there?</strong> Living far from family, in cold weather, in a language you are learning. Saying that you know it will be hard and you have thought about it is a better answer than saying it will be easy.</li>
<li><strong>Will you stay?</strong> Replacing a worker who leaves after four months costs them a great deal. They are listening for someone who understands the contract length and means it.</li>
</ol>

<h2>Practical things that help</h2>
<ul>
<li><strong>Be early and somewhere quiet</strong> with a charged phone and the best signal you can find. A call that keeps dropping is read as unreliability, fairly or not.</li>
<li><strong>Have your documents beside you</strong> — passport, certificates, exam results. Being asked for a number and having it is a small thing that reads well.</li>
<li><strong>Answer with examples, not adjectives.</strong> "I worked two years on a site in Qatar doing rebar" tells them more than "I am experienced".</li>
<li><strong>If you do not understand, say so.</strong> Through an interpreter especially. Guessing and answering the wrong question is worse than asking again.</li>
<li><strong>Greet them in their language if you can.</strong> Even a sentence. It demonstrates that you have started, which is the thing they are least able to verify any other way.</li>
</ul>

<h2>Questions worth asking them</h2>
<p>Being asked nothing is a small warning sign to an employer. Ask about the work itself: the shift pattern, who you would work with, what the accommodation is, whether overtime is normal. These are reasonable questions and asking them makes you look serious rather than demanding.</p>

<h2>Do not exaggerate</h2>
<p>Overstating your experience is the most common mistake, and it is found out on the first day of work rather than in the interview. An employer who finds out then will send you home, and you will have spent a year and a great deal of money for nothing.</p>
',
		);

		// -------------------------------------- published: document checklist
		$g['documents-you-will-need'] = array(
			'order'   => 4,
			'publish' => true,
			'title'   => 'The documents you will need, and when',
			'excerpt' => 'A checklist of what to gather, in the order it is actually asked for.',
			'body'    => '
<p>Nothing here is unusual, but people lose months because one item was left until it was urgent. Gather them in this order.</p>

<h2>Before you apply to anything</h2>
<ul>
<li><strong>Passport.</strong> The single thing that most often holds people up. If you do not have one, start now — everything else waits on it. It needs enough validity left to cover the contract.</li>
<li><strong>National ID (NID).</strong></li>
<li><strong>Passport-size photographs</strong> against a white background. You will need more than you expect.</li>
</ul>

<h2>When you apply</h2>
<ul>
<li><strong>Education certificates</strong>, if you have them. Much of this work does not require any — say so honestly rather than leaving the section empty.</li>
<li><strong>Trade or training certificates.</strong> Technical Training Centre certificates, safety courses, driving licences.</li>
<li><strong>Experience letters</strong> from previous employers where you can get them. A letter on a company pad with a phone number on it is worth having.</li>
<li><strong>Language certificates</strong> — JFT-Basic, JLPT, IELTS — if you have sat them.</li>
</ul>

<h2>After an employer selects you</h2>
<ul>
<li><strong>Medical certificate</strong> from an approved centre. Every destination requires one, and it is not a formality.</li>
<li><strong>Police clearance certificate.</strong></li>
<li><strong>BMET registration and the smart card.</strong> It is a legal requirement to leave Bangladesh for work, and it is also your record that you went through a proper channel.</li>
</ul>

<h2>Two rules about your documents</h2>
<p><strong>Keep the originals.</strong> Give copies. No legitimate agency needs to hold your original certificates indefinitely, and your passport should only leave your hands for the short periods it is physically required.</p>
<p><strong>Upload once.</strong> On this site, your documents stay on your file. You will not be asked for the same passport scan again for each new application.</p>
',
		);

		// ------------------------------------------- draft: passport process
		$g['getting-your-passport'] = array(
			'order'   => 5,
			'publish' => false,
			'title'   => 'Getting your passport',
			'excerpt' => 'The e-passport application, step by step.',
			'body'    => '
<p>A Bangladeshi e-passport is applied for online through the Department of Immigration and Passports, paid for through a bank or online channel, and completed in person at a regional passport office where your photograph and fingerprints are taken. Police verification follows.</p>
' . self::gap( 'the current fee bands and delivery times, and whether Marwa assists with the application or only advises on it.' ) . '
' . self::gap( 'which regional passport offices Marwa&#8217;s candidates most commonly use, and whether there is any guidance worth giving about appointment booking.' ) . '
<p><strong>Marwa does not charge you for this.</strong> Passport fees are government fees, paid by you, with a receipt in your own name.</p>
',
		);

		// --------------------------------------------- draft: medical process
		$g['the-medical'] = array(
			'order'   => 6,
			'publish' => false,
			'title'   => 'The medical examination',
			'excerpt' => 'What is checked, and what happens if something is found.',
			'body'    => '
<p>Every destination country requires a medical examination before a visa is issued, carried out at a centre that country approves. It typically covers general fitness, chest imaging, blood tests and eyesight.</p>
' . self::gap( 'which approved medical centres Marwa works with, for which destinations, and the current cost.' ) . '
' . self::gap( 'what Marwa&#8217;s practice is when a candidate is found temporarily unfit — whether the application is paused and retested, and after how long.' ) . '
<p>Being found unfit for one destination does not always mean being unfit for another, and a temporary condition is not the end of an application.</p>
',
		);

		return $g;
	}
}
