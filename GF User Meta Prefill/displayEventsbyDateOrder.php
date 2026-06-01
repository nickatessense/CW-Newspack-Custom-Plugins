<?php
/**
 * Plugin Name: Display Events by Date Order
 * Description: Grabs categories and displays events in date order.
 * Version: 1.0.17
 * Author: Verdian Insights
 */

function get_upcoming_events_query($args = array()) {
    $defaults = array(
        'limit'      => -1,
        'categories' => array('scheduled-webcasts'),
    );

    $args  = wp_parse_args($args, $defaults);
    $today = date('Ymd');

    return new WP_Query(array(
        'post_type'              => 'post',
        'posts_per_page'         => (int) $args['limit'],
        'meta_key'               => 'event_date',
        'orderby'                => 'meta_value_num',
        'order'                  => 'ASC',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'meta_query'             => array(
            array(
                'key'     => 'event_date',
                'value'   => $today,
                'compare' => '>=',
                'type'    => 'NUMERIC',
            ),
        ),
        'tax_query'              => array(
            array(
                'taxonomy' => 'category',
                'field'    => 'slug',
                'terms'    => (array) $args['categories'],
                'operator' => 'IN',
            ),
        ),
    ));
}
function upcoming_events_get_date_object($date_value) {
    if ( empty($date_value) ) {
        return false;
    }

    $date_obj = DateTime::createFromFormat('Ymd', $date_value);

    if ( ! $date_obj ) {
        $date_obj = date_create($date_value);
    }

    return $date_obj ?: false;
}
function render_upcoming_event_card($post_id) {
   $event_date     = get_field('event_date', $post_id);
    $event_end_date = get_field('event_end_date', $post_id);

    $start_date_obj = upcoming_events_get_date_object($event_date);
    $end_date_obj   = upcoming_events_get_date_object($event_end_date);

    $month = '';
    $day   = '';

    if ( $start_date_obj ) {
        $start_month = strtoupper($start_date_obj->format('M'));
        $start_day   = $start_date_obj->format('d');

        if ( $end_date_obj ) {
            $end_month = strtoupper($end_date_obj->format('M'));
            $end_day   = $end_date_obj->format('d');

            if ( $start_month === $end_month ) {
                // Same month
                $month = $start_month;
                $day   = $start_day . ' - ' . $end_day;
            } else {
                // Different month
                $month = $start_month . ' - ' . $end_month;
                $day   = $start_day . ' - ' . $end_day;
            }
        } else {
            // Single day
            $month = $start_month;
            $day   = $start_day;
        }
    }

    $permalink = get_permalink($post_id);
    $title     = get_the_title($post_id);
    $sponsors = array();

    if ( function_exists('\Newspack_Sponsors\get_all_sponsors') ) {
        $raw_sponsors = \Newspack_Sponsors\get_all_sponsors($post_id);

        if ( ! empty($raw_sponsors) && is_array($raw_sponsors) ) {
            foreach ( $raw_sponsors as $raw_sponsor ) {
                $sponsor = is_object($raw_sponsor) ? (array) $raw_sponsor : $raw_sponsor;

                if ( ! is_array($sponsor) ) {
                    continue;
                }

                $name = '';
                if ( ! empty($sponsor['sponsor_name']) ) {
                    $name = $sponsor['sponsor_name'];
                } elseif ( ! empty($sponsor['name']) ) {
                    $name = $sponsor['name'];
                } elseif ( ! empty($sponsor['post_title']) ) {
                    $name = $sponsor['post_title'];
                } elseif ( ! empty($sponsor['title']) ) {
                    $name = $sponsor['title'];
                }

                // $logo = '';
                // if ( ! empty($sponsor['sponsor_logo']) ) {
                //     if ( is_array($sponsor['sponsor_logo']) ) {
                //         $logo = $sponsor['sponsor_logo']['src'] ?? ($sponsor['sponsor_logo']['url'] ?? '');
                //     } elseif ( is_object($sponsor['sponsor_logo']) ) {
                //         $logo_obj = (array) $sponsor['sponsor_logo'];
                //         $logo = $logo_obj['src'] ?? ($logo_obj['url'] ?? '');
                //     } elseif ( is_string($sponsor['sponsor_logo']) ) {
                //         $logo = $sponsor['sponsor_logo'];
                //     }
                // } elseif ( ! empty($sponsor['logo']) ) {
                //     if ( is_array($sponsor['logo']) ) {
                //         $logo = $sponsor['logo']['src'] ?? ($sponsor['logo']['url'] ?? '');
                //     } elseif ( is_object($sponsor['logo']) ) {
                //         $logo_obj = (array) $sponsor['logo'];
                //         $logo = $logo_obj['src'] ?? ($logo_obj['url'] ?? '');
                //     } elseif ( is_string($sponsor['logo']) ) {
                //         $logo = $sponsor['logo'];
                //     }
                // }

                $link = '';
                if ( ! empty($sponsor['sponsor_url']) ) {
                    $link = $sponsor['sponsor_url'];
                } elseif ( ! empty($sponsor['url']) ) {
                    $link = $sponsor['url'];
                } elseif ( ! empty($sponsor['link']) ) {
                    $link = $sponsor['link'];
                } elseif ( ! empty($sponsor['permalink']) ) {
                    $link = $sponsor['permalink'];
                }

                // if ( $name || $logo ) {
                if ( $name ) {
                    // $sponsors[] = array(
                    //     'name' => $name,
                    //     'logo' => $logo,
                    //     'link' => $link,
                    // );
                    $sponsors[] = array(
                        'name' => $name,
                        'link' => $link,
                    );
                }
            }
        }
    }

    ob_start();
    ?>
    <article class="upcoming-event-card" data-post-id="<?php echo esc_attr($post_id); ?>">
        <?php if ( $month && $day ) : ?>
            <div class="upcoming-event-card__date" aria-hidden="true">
                <span class="upcoming-event-card__month"><?php echo esc_html($month); ?></span>
                <span class="upcoming-event-card__day"><?php echo esc_html($day); ?></span>
            </div>
        <?php endif; ?>

        <div class="upcoming-event-card__content">
            <h2 class="upcoming-event-card__title">
                <a href="<?php echo esc_url($permalink); ?>" rel="bookmark">
                    <?php echo esc_html($title); ?>
                </a>
            </h2>

            <?php if ( ! empty($sponsors) ) : ?>
                <div class="upcoming-event-card__sponsors">
                    <?php foreach ( $sponsors as $sponsor ) : ?>
                        <div class="upcoming-event-card__sponsor">
                            <?php if ( ! empty($sponsor['logo']) ) : ?>
                                <div class="upcoming-event-card__sponsor-logo">
                                    <?php if ( ! empty($sponsor['link']) ) : ?>
                                        <a href="<?php echo esc_url($sponsor['link']); ?>" target="_blank" rel="noopener">
                                            <img src="<?php echo esc_url($sponsor['logo']); ?>" alt="<?php echo esc_attr($sponsor['name']); ?>">
                                        </a>
                                    <?php else : ?>
                                        <img src="<?php echo esc_url($sponsor['logo']); ?>" alt="<?php echo esc_attr($sponsor['name']); ?>">
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ( ! empty($sponsor['name']) ) : ?>
                                <div class="upcoming-event-card__sponsor-name">
                                    Provided by
                                    <?php if ( ! empty($sponsor['link']) ) : ?>
                                        <a href="<?php echo esc_url($sponsor['link']); ?>" target="_blank" rel="noopener">
                                            <?php echo esc_html($sponsor['name']); ?>
                                        </a>
                                    <?php else : ?>
                                        <span><?php echo esc_html($sponsor['name']); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </article>
    <?php
    return ob_get_clean();
}

function upcoming_events_shortcode($atts) {
    $atts = shortcode_atts(array(
        'categories' => 'scheduled-webcasts',
        'limit'      => -1,
        'class'      => '',
    ), $atts, 'upcoming_events');

    $categories = array_filter(array_map('trim', explode(',', $atts['categories'])));
    $limit      = (int) $atts['limit'];
    $extra_class = trim($atts['class']);

    $query = get_upcoming_events_query(array(
        'limit'      => $limit,
        'categories' => $categories,
    ));

    $classes = 'upcoming-events-list';
    if ( $extra_class ) {
        $classes .= ' ' . esc_attr($extra_class);
    }

    ob_start();

    if ( $query->have_posts() ) {
        echo '<div class="' . $classes . '">';

        while ( $query->have_posts() ) {
            $query->the_post();
            echo render_upcoming_event_card(get_the_ID());
        }

        echo '</div>';
        wp_reset_postdata();
    } else {
        echo '<p>No upcoming events found.</p>';
    }

    return ob_get_clean();
}
add_shortcode('upcoming_events', 'upcoming_events_shortcode');

function upcoming_events_inline_styles() {
    ?>
    <style>
        .upcoming-events-list {
            display: flex;
            flex-direction: column;
            gap: 34px;
        }

        .upcoming-event-card {
            display: grid;
            grid-template-columns: 118px 1fr;
            gap: 28px;
            align-items: start;
        }

        .upcoming-event-card__date {
            border-radius: 8px;
            background: #d9d9d9;
            text-align: center;
        }

        .upcoming-event-card__month {
            display: block;
            background: #7898d1;
            color: #fff;
            font-size: 18px;
            font-weight: 700;
            line-height: 1;
            text-transform: uppercase;
            letter-spacing: 1px;
            white-space: nowrap;
            padding: 6px 8px;
            border-radius: 8px 8px 0 0;
        }

        .upcoming-event-card__day {
            display: block;
            color: #000;
            font-size: 24px;
            font-weight: 700;
            line-height: .92;
            padding: 10px 8px;
        }

        .upcoming-event-card__content {
            min-width: 0;
        }

        .upcoming-event-card__title {
            margin: 0 0 16px;
            font-size: clamp(30px, 3vw, 42px);
            line-height: 1.08;
        }

        .upcoming-event-card__title a {
            color: inherit;
            text-decoration: none;
        }

        .upcoming-event-card__title a:hover {
            text-decoration: underline;
        }

        .upcoming-event-card__sponsors {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .upcoming-event-card__sponsor {
            display: block;
        }

        .upcoming-event-card__sponsor-logo {
            margin-bottom: 8px;
        }

        .upcoming-event-card__sponsor-logo img {
            display: block;
            width: auto;
            height: auto;
            max-width: 120px;
            max-height: 20px;
            object-fit: contain;
        }

        .upcoming-event-card__sponsor-name {
            color: #666;
            font-size: 14px;
            line-height: 1.35;
        }

        .upcoming-event-card__sponsor-name a {
            color: inherit !important;
            text-decoration: none;
        }

        .upcoming-event-card__sponsor-name a:hover {
            text-decoration: underline;
        }

        /* ✅ APPLY SAME STYLES TO BOTH CLASSES */
        .upcomingVirtual .upcoming-event-card__date,
        .upcomingInperson .upcoming-event-card__date {
            background: #d9d9d9;
        }

        .upcomingVirtual .upcoming-event-card__month,
        .upcomingInperson .upcoming-event-card__month {
            background: #7898d1;
            color: #fff;
        }

        .upcomingVirtual .upcoming-event-card__day,
        .upcomingInperson .upcoming-event-card__day {
            color: #000;
        }

        .upcomingVirtual .upcoming-event-card__sponsor-name,
        .upcomingInperson .upcoming-event-card__sponsor-name {
            color: #666;
        }
        .upcoming-event-card__title {
            font-size: 18px;
        }
        .upcoming-event-card__excerpt {
            margin: 0 0 16px;
            font-size: 15px;
            line-height: 1.5;
            color: #444;
        }
        @media (max-width: 700px) {
            .upcoming-event-card {
                grid-template-columns: 92px 1fr;
                gap: 18px;
            }

            .upcoming-event-card__date {
                width: 92px;
            }

            .upcoming-event-card__month {
                font-size: 20px;
                padding: 10px 6px;
            }

            .upcoming-event-card__day {
                font-size: 64px;
                padding: 10px 6px 12px;
            }

            .upcoming-event-card__title {
                font-size: 28px;
            }
        }

        @media (max-width: 520px) {
            .upcoming-event-card {
                grid-template-columns: 1fr;
            }

            .upcoming-event-card__date {
                width: 92px;
            }
        }

        .upcoming-event-card__month,
        .upcoming-event-card__day {
            white-space: nowrap;
        }
       /* Excerpt version only */
        .upcoming-events-list--with-excerpt {
            gap: 70px;
        }

        .upcoming-events-list--with-excerpt .upcoming-event-card {
            display: grid;
            grid-template-columns: 165px 1fr;
            gap: 64px;
            align-items: start;
        }

        .upcoming-events-list--with-excerpt .upcoming-event-card__date {
            width: 165px;
            border-radius: 10px;
            overflow: hidden;
        }

        .upcoming-events-list--with-excerpt .upcoming-event-card__month {
            font-size: 36px;
            padding: 8px 10px;
            border-radius: 10px 10px 0 0;
        }

        .upcoming-events-list--with-excerpt .upcoming-event-card__day {
            font-size: 90px;
            line-height: 1;
            padding: 12px 10px 18px;
        }

        .upcoming-events-list--with-excerpt .upcoming-event-card__title {
            margin: 0 0 10px;
            font-size: 27px;
            line-height: 1.08;
            font-weight: 700;
        }

        .upcoming-events-list--with-excerpt .upcoming-event-card__excerpt {
            margin: 0 0 14px;
            font-size: 17px;
            line-height: 1.55;
            color: #000;
        }

        .upcoming-events-list--with-excerpt .upcoming-event-card__sponsors {
            gap: 0;
        }

        .upcoming-events-list--with-excerpt .upcoming-event-card__sponsor-name {
            margin: 0;
            font-size: 16px;
            line-height: 1.4;
            color: #555;
        }
        .upcoming-events-list--with-excerpt .upcoming-event-card__sponsor {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .upcoming-events-list--with-excerpt .upcoming-event-card__sponsor-logo {
            margin: 0;
        }

        .upcoming-events-list--with-excerpt .upcoming-event-card__sponsor-logo img {
            max-width: 90px;
            max-height: 24px;
            width: auto;
            height: auto;
            display: block;
        }
    </style>
    <?php
}
add_action('wp_head', 'upcoming_events_inline_styles');
add_shortcode('upcoming_events', 'upcoming_events_shortcode');

add_filter('login_redirect', 'custom_login_redirect', 10, 3);
function custom_login_redirect($redirect_to, $request, $user) {
    if (isset($user->roles) && is_array($user->roles)) {
        return home_url();
    }
    return $redirect_to;
}
function render_upcoming_event_card_with_excerpt($post_id) {
    $event_date     = get_field('event_date', $post_id);
    $event_end_date = get_field('event_end_date', $post_id);

    $start_date_obj = upcoming_events_get_date_object($event_date);
    $end_date_obj   = upcoming_events_get_date_object($event_end_date);

    $month = '';
    $day   = '';

    if ( $start_date_obj ) {
        $start_month = strtoupper($start_date_obj->format('M'));
        $start_day   = $start_date_obj->format('d');

        if ( $end_date_obj ) {
            $end_month = strtoupper($end_date_obj->format('M'));
            $end_day   = $end_date_obj->format('d');

            if ( $start_month === $end_month ) {
                $month = $start_month;
                $day   = $start_day . ' - ' . $end_day;
            } else {
                $month = $start_month . ' - ' . $end_month;
                $day   = $start_day . ' - ' . $end_day;
            }
        } else {
            $month = $start_month;
            $day   = $start_day;
        }
    }

    $permalink = get_permalink($post_id);
    $title     = get_the_title($post_id);
    $excerpt   = get_the_excerpt($post_id);
    $sponsors  = array();

    if ( function_exists('\Newspack_Sponsors\get_all_sponsors') ) {
        $raw_sponsors = \Newspack_Sponsors\get_all_sponsors($post_id);

        if ( ! empty($raw_sponsors) && is_array($raw_sponsors) ) {
            foreach ( $raw_sponsors as $raw_sponsor ) {
                $sponsor = is_object($raw_sponsor) ? (array) $raw_sponsor : $raw_sponsor;

                if ( ! is_array($sponsor) ) {
                    continue;
                }

                $name = '';

                if ( ! empty($sponsor['sponsor_name']) ) {
                    $name = $sponsor['sponsor_name'];
                } elseif ( ! empty($sponsor['name']) ) {
                    $name = $sponsor['name'];
                } elseif ( ! empty($sponsor['post_title']) ) {
                    $name = $sponsor['post_title'];
                } elseif ( ! empty($sponsor['title']) ) {
                    $name = $sponsor['title'];
                }

                $link = '';

                if ( ! empty($sponsor['sponsor_url']) ) {
                    $link = $sponsor['sponsor_url'];
                } elseif ( ! empty($sponsor['url']) ) {
                    $link = $sponsor['url'];
                } elseif ( ! empty($sponsor['link']) ) {
                    $link = $sponsor['link'];
                } elseif ( ! empty($sponsor['permalink']) ) {
                    $link = $sponsor['permalink'];
                }

                $logo = '';
                if ( ! empty($sponsor['sponsor_logo']) ) {
                    if ( is_array($sponsor['sponsor_logo']) ) {
                        $logo = $sponsor['sponsor_logo']['src'] ?? ($sponsor['sponsor_logo']['url'] ?? '');
                    } elseif ( is_object($sponsor['sponsor_logo']) ) {
                        $logo_obj = (array) $sponsor['sponsor_logo'];
                        $logo = $logo_obj['src'] ?? ($logo_obj['url'] ?? '');
                    } elseif ( is_string($sponsor['sponsor_logo']) ) {
                        $logo = $sponsor['sponsor_logo'];
                    }
                } elseif ( ! empty($sponsor['logo']) ) {
                    if ( is_array($sponsor['logo']) ) {
                        $logo = $sponsor['logo']['src'] ?? ($sponsor['logo']['url'] ?? '');
                    } elseif ( is_object($sponsor['logo']) ) {
                        $logo_obj = (array) $sponsor['logo'];
                        $logo = $logo_obj['src'] ?? ($logo_obj['url'] ?? '');
                    } elseif ( is_string($sponsor['logo']) ) {
                        $logo = $sponsor['logo'];
                    }
                }
                if ( $name || $logo ) {
                    $sponsors[] = array(
                        'name' => $name,
                        'logo' => $logo,
                        'link' => $link,
                    );
                }
            }
        }
    }

    ob_start();
    ?>
    <article class="upcoming-event-card" data-post-id="<?php echo esc_attr($post_id); ?>">
        <?php if ( $month && $day ) : ?>
            <div class="upcoming-event-card__date" aria-hidden="true">
                <span class="upcoming-event-card__month"><?php echo esc_html($month); ?></span>
                <span class="upcoming-event-card__day"><?php echo esc_html($day); ?></span>
            </div>
        <?php endif; ?>

        <div class="upcoming-event-card__content">
            <h2 class="upcoming-event-card__title">
                <a href="<?php echo esc_url($permalink); ?>" rel="bookmark">
                    <?php echo esc_html($title); ?>
                </a>
            </h2>

            <?php if ( ! empty($excerpt) ) : ?>
                <div class="upcoming-event-card__excerpt">
                    <?php echo wp_kses_post(wpautop($excerpt)); ?>
                </div>
            <?php endif; ?>

           <?php if ( ! empty($sponsors) ) : ?>
                <div class="upcoming-event-card__sponsors">
                    <?php foreach ( $sponsors as $sponsor ) : ?>
                        <div class="upcoming-event-card__sponsor">

                            <?php if ( ! empty($sponsor['logo']) ) : ?>
                                <div class="upcoming-event-card__sponsor-logo">
                                    <?php if ( ! empty($sponsor['link']) ) : ?>
                                        <a href="<?php echo esc_url($sponsor['link']); ?>" target="_blank" rel="noopener">
                                            <img src="<?php echo esc_url($sponsor['logo']); ?>" alt="<?php echo esc_attr($sponsor['name']); ?>">
                                        </a>
                                    <?php else : ?>
                                        <img src="<?php echo esc_url($sponsor['logo']); ?>" alt="<?php echo esc_attr($sponsor['name']); ?>">
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ( ! empty($sponsor['name']) ) : ?>
                                <div class="upcoming-event-card__sponsor-name">
                                    Provided by
                                    <?php if ( ! empty($sponsor['link']) ) : ?>
                                        <a href="<?php echo esc_url($sponsor['link']); ?>" target="_blank" rel="noopener">
                                            <?php echo esc_html($sponsor['name']); ?>
                                        </a>
                                    <?php else : ?>
                                        <span><?php echo esc_html($sponsor['name']); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </article>
    <?php
    return ob_get_clean();
}

function upcoming_events_with_excerpt_shortcode($atts) {
    $atts = shortcode_atts(array(
        'categories' => 'scheduled-webcasts',
        'limit'      => -1,
        'class'      => '',
    ), $atts, 'upcoming_events_with_excerpt');

    $categories  = array_filter(array_map('trim', explode(',', $atts['categories'])));
    $limit       = (int) $atts['limit'];
    $extra_class = trim($atts['class']);

    $query = get_upcoming_events_query(array(
        'limit'      => $limit,
        'categories' => $categories,
    ));

    $classes = 'upcoming-events-list upcoming-events-list--with-excerpt';
    if ( $extra_class ) {
        $classes .= ' ' . esc_attr($extra_class);
    }

    ob_start();

    if ( $query->have_posts() ) {
        echo '<div class="' . esc_attr($classes) . '">';

        while ( $query->have_posts() ) {
            $query->the_post();
            echo render_upcoming_event_card_with_excerpt(get_the_ID());
        }

        echo '</div>';
        wp_reset_postdata();
    } else {
        echo '<p>No upcoming events found.</p>';
    }

    return ob_get_clean();
}
add_shortcode('upcoming_events_with_excerpt', 'upcoming_events_with_excerpt_shortcode');