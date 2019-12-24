<?php

class ControllerModuleAfterCheckout extends Controller
{

    public function index()
    {
        $this->load->language( "module/after_checkout" );
    }

    // get filter association category
    private function afterCheckoutGetLastOrderId()
    {
        $this->load->model( 'module/after_checkout' );
        return $this->model_module_after_checkout->afterCheckoutGetLastOrderId();
    }

    // Add/Update Cart
    public function afterCheckoutUpdateOrder()
    {
        $json = array();
        $json['error'] = false;


        //Get params
        $getOrderId = ( isset( $this->request->post['order_id'] ) ) ? htmlspecialchars( $this->request->post['order_id'] ) : '';
        $getProducts = ( isset ( $this->request->post['products'] ) ) ? htmlspecialchars( $this->request->post['products'] ) : '';

        $new_total = 0;
        $order_data = array();
        $this->load->model( 'checkout/order' );
        $this->load->model( 'module/after_checkout' );

        try {

            if ( !empty( $getProducts ) && isset( $getOrderId ) ) {

                //получаем данные о сделанном заказе
                $order_info = $this->model_checkout_order->getOrder( $getOrderId );
                if ( $order_info ) {

                    $order_data['products'] = array();

                    // ------------------------------------------------------ Add Products
                    $newProducts = array();
                    $products_array = explode( ',', $getProducts );
                    foreach ( $products_array as $product_id ) {
                        $newProducts[] = $this->model_module_after_checkout->afterCheckoutGetProductStatusOff( $product_id );
                    }

                    if ( $newProducts ) {
                        foreach ( $newProducts as $x => $product ) { // compact array
                            if ( strlen( $product['special'] ) > 0 ) { // if special price

                                $order_data['products'][] = array(
                                    'product_id' => $product['product_id'],
                                    'name' => $product['name'],
                                    'model' => $product['model'],
                                    'option' => array(),
                                    'quantity' => 1,
                                    'subtract' => number_format( $product['subtract'], 4, '.', '' ),
                                    'tax_class_id' => number_format( $product['tax_class_id'], 4, '.', '' ),
                                    'price' => $this->tax->calculate( $product['special'], $product['tax_class_id'], $this->config->get( 'config_tax' ) ),
                                    'total' => $this->tax->calculate( $product['special'], $product['tax_class_id'], $this->config->get( 'config_tax' ) ),
                                );
                                $new_total += (float)$this->tax->calculate( $product['special'], $product['tax_class_id'], $this->config->get( 'config_tax' ) );

                            } else {

                                $order_data['products'][] = array(
                                    'product_id' => $product['product_id'],
                                    'name' => $product['name'],
                                    'model' => $product['model'],
                                    'option' => array(),
                                    'quantity' => 1,
                                    'subtract' => number_format( $product['subtract'], 4, '.', '' ),
                                    'tax_class_id' => number_format( $product['tax_class_id'], 4, '.', '' ),
                                    'price' => $this->tax->calculate( $product['price'], $product['tax_class_id'], $this->config->get( 'config_tax' ) ),
                                    'total' => $this->tax->calculate( $product['price'], $product['tax_class_id'], $this->config->get( 'config_tax' ) ),
                                );
                                $new_total += (float)$this->tax->calculate( $product['price'], $product['tax_class_id'], $this->config->get( 'config_tax' ) );

                            }

                        }

                    } else {
                        $json['error'] = true;
                    }

                    // ------------------------------------------------------ Get Last Order
                    //получаем данные о последнем заказа по идентификатору
                    $oldProducts = $this->model_module_after_checkout->afterCheckoutGetLastOrder( $getOrderId );
                    $old_products = array();
                    if ( $oldProducts ) {

                        foreach ( $oldProducts as $x => $total ) {

                            $old_products['order_id'] = $total['order_id'];
                            // this sub_total
                            if ( $total['code'] == 'sub_total' ) {
                                $old_products['sub_total'] = $total['value'];
                            }
                            // this total
                            if ( $total['code'] == 'total' ) {
                                $old_products['total'] = $total['value'];
                            }
                            // this if not sub_total and total
                            if ( $total['code'] != 'sub_total' && $total['code'] != 'total' ) {
                                @$old_products['other'] += $total['value'];
                            }
                        }

                    } else {
                        $json['error'] = true;
                    }

                    // ------------------------------------------------------ Validate summary Order
                    $a = $this->ach_format( $old_products['sub_total'] ); // сумма старого заказа
                    $c = $this->ach_format( $old_products['total'] - $old_products['sub_total'] ); // сумма разницы между предварительной суммой и общей до нового заказа
                    $order_data['sub_total'] = $this->ach_format( $a + $new_total ); // сумма нового заказа
                    $order_data['total'] = $this->ach_format( $order_data['sub_total'] + $c ); // общая сумма
                    //$after_checkout_update = true;
                    $after_checkout_update = $this->model_module_after_checkout->afterCheckoutUpdateOrder( $getOrderId, $order_data );


                    // ------------------------------------------------------ Send Email

                    // ------------------------------------------------------ Send SMS


                    if ( !$after_checkout_update ) {
                        $this->log->write('Ошибка обновления данных ордера заказа 1 !');
                        $json['error'] = true;
                    }

                } else {
                    $this->log->write("Тестовый режим модуля \"Заказ плюс\". Попытка записи новой суммы в ордер заказа.");
                    $json['error'] = true;

                }//end if order info

                unset( $oldProducts );
                unset( $old_products );
                unset( $newProducts );
                unset( $products_array );

            } else {
                $this->log->write('Нет данных в значениях $getProducts и $getOrderId');
                $json['error'] = true;
            }

            $this->cart->clear();
            unset( $getOrderId );
            unset( $order_info );
            unset( $getProducts );

        } catch ( Exception $e ) {

            $this->log->write('Ошибка обновления данных ордера заказа 2 !');
            $this->log->write($e->getMessage());
            $json['error'] = true;

        }

        if ( isset( $this->request->server['HTTP_ORIGIN'] ) ) {
            $this->response->addHeader( 'Access-Control-Allow-Origin: ' . $this->request->server['HTTP_ORIGIN'] );
            $this->response->addHeader( 'Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS' );
            $this->response->addHeader( 'Access-Control-Max-Age: 1000' );
            $this->response->addHeader( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With' );
        }

        $this->response->addHeader( 'Content-Type: application/json' );
        $this->response->setOutput( json_encode( $json ) );
    }

    // Pages
    public function afterCheckoutGetPage()
    {
        $this->load->model( 'module/after_checkout' );
        $data = array();
        $data['after_checkout_themes'] = ( strlen( $this->config->get( "after_checkout_page_themes" ) ) > 0 ) ? $this->config->get( "after_checkout_page_themes" ) : 0; //themes

        if ( $this->config->get( 'after_checkout_information' ) ) {

            $data['after_checkout_pages'] = array();
            $data['after_checkout_pages_count'] = 0;


            $page = ( isset( $this->request->post['page'] ) ) ? htmlspecialchars( $this->request->post['page'] ) : false; //int pages
            if ( !$page ) {

                $after_checkout_pages = $this->model_module_after_checkout->afterCheckoutGetPages( $this->config->get( 'after_checkout_information' ) ); // Get Pages
                if( $after_checkout_pages ){
                    $after_checkout_pages = $after_checkout_pages[0];
                    $data['after_checkout_pages']['title'] = $after_checkout_pages['title'];
                    $data['after_checkout_pages']['description'] = $this->afterCheckoutFormatDescription( $after_checkout_pages['description'] );
                }

            } else {

                $target = ( isset( $this->request->post['target'] ) ) ? htmlspecialchars( $this->request->post['target'] ) : 0;
                $data['after_checkout_pages_count'] = htmlspecialchars( $this->request->post['page'] );
                $after_checkout_products = ( isset( $this->request->post['products'] ) ) ? htmlspecialchars( $this->request->post['products'] ) : 0;

                if ( !empty( $after_checkout_products ) ) {
                    if ( strlen( $after_checkout_products ) > 1 ) {
                        $after_checkout_products_array = explode( ',', $after_checkout_products );
                    } else {
                        $after_checkout_products_array = $after_checkout_products;
                    }
                }

                if ( $target ) {
                    $after_checkout_pages = $this->model_module_after_checkout->afterCheckoutGetPagesId( $target ); // Get Pages
                    $data['after_checkout_pages']['title'] = $after_checkout_pages['title'];
                    $data['after_checkout_pages']['description'] = $this->afterCheckoutFormatDescription( $after_checkout_pages['description'] );
                    $data['after_checkout_pages']['keywords'] = @$after_checkout_products_array;
                }
            }
            $this->response->setOutput( $this->afterCheckoutTemplate( DIR_SYSTEM . 'library/local_script/after_checkout/template/template.tpl', $data ) );

        } else {

            die();
        }

    }

    // Format
    private function afterCheckoutFormatDescription( $description )
    {

        $new_description = $description;

        // if enabled format tags
        if ( $this->config->get( "after_checkout_enable_tags" ) ) {

            preg_match_all( '/\{(.+?)\}/', $description, $template );

            if ( !empty( $template ) ) { //если есть вообще теги на странице

                if ( count( $template[0] ) > 0 ) {


                    $arData = array();
                    $arCt = array();
                    $arCategory = array();
                    $arPt = array();
                    $arProduct = array();
                    $a = 0;
                    $b = 0;

                    /// Обработка массивов
                    // Work Category
                    foreach ( $template[0] as $key ) {
                        if ( strpos( $key, 'category=' ) ) {
                            $arCt[] = str_replace( "category=", "", $template[1][$a] );
                        }
                        $a++;
                    }
                    unset( $a );

                    // Work Products
                    foreach ( $template[0] as $key ) {
                        if ( strpos( $key, 'product=' ) ) {
                            $arPt[] = str_replace( "product=", "", $template[1][$b] );
                        }
                        $b++;
                    }
                    unset( $b );


                    // Get source
                    if ( count( $arCt ) > 0 ) {
                        foreach ( $arCt as $key ) {
                            $arCategory[] = htmlentities( $this->afterCheckoutGetCategory( $key ) );
                        }
                    }

                    if ( count( $arPt ) > 0 ) {
                        foreach ( $arPt as $key ) {
                            $arProduct[] = htmlentities( $this->afterCheckoutGetProduct( $key ) );
                        }
                    }

                    $point1 = 0;
                    foreach ( $template[0] as $k ) {
                        if ( strpos( $k, "category=" ) ) {
                            $new_description = str_replace( $k, html_entity_decode( $arCategory[$point1], ENT_QUOTES, 'UTF-8' ), $new_description );
                            $point1++;
                        }
                    }

                    $point2 = 0;
                    foreach ( $template[0] as $k ) {
                        if ( strpos( $k, "product=" ) ) {
                            $new_description = str_replace( $k, html_entity_decode( $arProduct[$point2], ENT_QUOTES, 'UTF-8' ), $new_description );
                            $point2++;
                        }
                    }


                    $c = 0;
                    foreach ( $template[0] as $key ) {

                        if ( strpos( $new_description, "{latest}" ) ) {
                            $arData['latest'] = $this->afterCheckoutGetLatest();
                            if ( $arData['latest'] ) {
                                $new_description = str_replace( "{latest}", $arData['latest'], $new_description );
                            } else {
                                $new_description = str_replace( "{latest}", "Not found !", $new_description );
                            }
                        }
                        sleep( 0.3 );
                        if ( strpos( $new_description, "{bestseller}" ) ) {
                            $arData['bestseller'] = $this->afterCheckoutGetBestseller();
                            if ( $arData['bestseller'] ) {
                                $new_description = str_replace( "{bestseller}", $arData['bestseller'], $new_description );
                            } else {
                                $new_description = str_replace( "{bestseller}", "Not found !", $new_description );
                            }
                        }
                        sleep( 0.3 );
                        if ( strpos( $new_description, "{special}" ) ) {
                            $arData['special'] = $this->afterCheckoutGetSpecial();
                            if ( $arData['special'] ) {
                                $new_description = str_replace( "{special}", $arData['special'], $new_description );
                            } else {
                                $new_description = str_replace( "{special}", "Not found !", $new_description );
                            }
                        }
                        sleep( 0.3 );
                        if ( strpos( $new_description, "{association}" ) ) {
                            $arData['association'] = $this->afterCheckoutGetAssociations();
                            if ( $arData['association'] ) {
                                $new_description = str_replace( "{association}", $arData['association'], $new_description );
                            } else {
                                $new_description = str_replace( "{association}", "Not found !", $new_description );
                            }
                        }
                        $c++;
                    }
                    unset( $arData );
                    unset( $template );
                    unset( $c );
                    unset( $arCt );
                    unset( $arCategory );
                    unset( $arPt );
                    unset( $arProduct );

                }

            }
        }
        return html_entity_decode( $new_description, ENT_QUOTES, 'UTF-8' );
    }


    // Template: Category
    private function afterCheckoutGetCategory( $category_id )
    {
        $this->load->model( 'catalog/category' );
        $this->load->model( 'catalog/product' );
        $this->load->model( 'tool/image' );
        $this->load->language( 'product/category' );
        $this->load->language( "module/after_checkout" );

        // users
        $limit = ( strlen( $this->config->get( "after_checkout_count_output_product" ) ) > 0 ) ? $this->config->get( "after_checkout_count_output_product" ) : 10;
        $button = $this->config->get( "after_checkout_button" );
        if ( isset( $button['add'] ) ) {
            $data['button_addons'] = $button['add'];
        } else {
            $data['button_addons'] = $this->language->get( 'button_addons' );
        }
        if ( isset( $button['continue'] ) ) {
            $data['after_checkout_button_finish'] = $button['continue'];
        } else {
            $data['after_checkout_button_finish'] = $this->language->get( 'after_checkout_button_finish' );
        }
        unset( $button );

        $data['after_checkout_themes'] = ( strlen( $this->config->get( "after_checkout_page_themes" ) ) > 0 ) ? $this->config->get( "after_checkout_page_themes" ) : 0; //themes
        $data['button_addons_success'] = $this->language->get( 'button_addons_success' );
        $data['button_addons_success_checked'] = $this->language->get( 'button_addons_success_checked' );

        $category_info = $this->model_module_after_checkout->afterCheckoutGetCategory( $category_id );

        if ( $category_info ) {
            $data['title'] = $category_info['name'];
            $data['text_refine'] = $this->language->get( 'text_refine' );
            $data['text_empty'] = $this->language->get( 'text_empty' );
            $data['text_quantity'] = $this->language->get( 'text_quantity' );
            $data['text_manufacturer'] = $this->language->get( 'text_manufacturer' );
            $data['text_model'] = $this->language->get( 'text_model' );
            $data['text_price'] = $this->language->get( 'text_price' );
            $data['text_tax'] = $this->language->get( 'text_tax' );
            $data['text_points'] = $this->language->get( 'text_points' );
            $data['text_compare'] = sprintf( $this->language->get( 'text_compare' ), ( isset( $this->session->data['compare'] ) ? count( $this->session->data['compare'] ) : 0 ) );
            $data['text_sort'] = $this->language->get( 'text_sort' );
            $data['text_limit'] = $this->language->get( 'text_limit' );
            $data['button_cart'] = $this->language->get( 'button_cart' );
            $data['button_wishlist'] = $this->language->get( 'button_wishlist' );
            $data['button_compare'] = $this->language->get( 'button_compare' );
            $data['button_continue'] = $this->language->get( 'button_continue' );
            $data['button_list'] = $this->language->get( 'button_list' );
            $data['button_grid'] = $this->language->get( 'button_grid' );


            $data['products'] = array();
            $filter_data = array(
                'filter_category_id' => $category_id,
                'sort' => 'p.sort_order',
                'order' => 'ASC',
                'start' => 0,
                'limit' => $limit
            );

            $results = $this->model_catalog_product->getProducts( $filter_data );

            foreach ( $results as $result ) {
                if ( $result['image'] ) {
                    $image = $this->model_tool_image->resize( $result['image'], $this->config->get( 'config_image_product_width' ), $this->config->get( 'config_image_product_height' ) );
                } else {
                    $image = $this->model_tool_image->resize( 'placeholder.png', $this->config->get( 'config_image_product_width' ), $this->config->get( 'config_image_product_height' ) );
                }

                if ( ( $this->config->get( 'config_customer_price' ) && $this->customer->isLogged() ) || !$this->config->get( 'config_customer_price' ) ) {
                    $price = $this->currency->format( $this->tax->calculate( $result['price'], $result['tax_class_id'], $this->config->get( 'config_tax' ) ) );
                } else {
                    $price = false;
                }

                if ( (float)$result['special'] ) {
                    $special = $this->currency->format( $this->tax->calculate( $result['special'], $result['tax_class_id'], $this->config->get( 'config_tax' ) ) );
                } else {
                    $special = false;
                }

                if ( $this->config->get( 'config_tax' ) ) {
                    $tax = $this->currency->format( (float)$result['special'] ? $result['special'] : $result['price'] );
                } else {
                    $tax = false;
                }

                if ( $this->config->get( 'config_review_status' ) ) {
                    $rating = (int)$result['rating'];
                } else {
                    $rating = false;
                }

                $data['products'][] = array(
                    'product_id' => $result['product_id'],
                    'thumb' => $image,
                    'name' => $result['name'],
                    'description' => utf8_substr( strip_tags( html_entity_decode( $result['description'], ENT_QUOTES, 'UTF-8' ) ), 0, $this->config->get( 'config_product_description_length' ) ) . '..',
                    'price' => $price,
                    'special' => $special,
                    'tax' => $tax,
                    'minimum' => $result['minimum'] > 0 ? $result['minimum'] : 1,
                    'rating' => $result['rating'],
                    'href' => $this->url->link( 'product/product', '&product_id=' . $result['product_id'] ),
                    'view' => $result['viewed']
                );
            }


            //random array
            if ( $this->config->get( 'after_checkout_random_output_product' ) == 'on' ) {
                shuffle( $data['products'] );
            }

            $file = DIR_SYSTEM . 'library/local_script/after_checkout/template/category.tpl';
            if ( file_exists( $file ) ) {
                return $this->afterCheckoutTemplate( $file, $data );
            }

        }
        return 'Not found !';
    }

    // Template: Product
    private function afterCheckoutGetProduct( $product_id )
    {
        $this->load->language( "module/after_checkout" );
        $data['after_checkout_themes'] = ( strlen( $this->config->get( "after_checkout_page_themes" ) ) > 0 ) ? $this->config->get( "after_checkout_page_themes" ) : 0; //themes

        $button = $this->config->get( "after_checkout_button" );
        if ( isset( $button['add'] ) ) {
            $data['button_addons'] = $button['add'];
        } else {
            $data['button_addons'] = $this->language->get( 'button_addons' );
        }
        if ( isset( $button['continue'] ) ) {
            $data['after_checkout_button_finish'] = $button['continue'];
        } else {
            $data['after_checkout_button_finish'] = $this->language->get( 'after_checkout_button_finish' );
        }
        unset( $button );

        $data['after_checkout_themes'] = ( strlen( $this->config->get( "after_checkout_page_themes" ) ) > 0 ) ? $this->config->get( "after_checkout_page_themes" ) : 0; //themes
        $data['button_addons_success'] = $this->language->get( 'button_addons_success' );
        $data['button_addons_success_checked'] = $this->language->get( 'button_addons_success_checked' );

        $this->load->language( "product/product" );
        $data['text_select'] = $this->language->get( 'text_select' );
        $data['text_manufacturer'] = $this->language->get( 'text_manufacturer' );
        $data['text_model'] = $this->language->get( 'text_model' );
        $data['text_not_price'] = $this->language->get( 'text_not_price' );
        $data['text_reward'] = $this->language->get( 'text_reward' );
        $data['text_points'] = $this->language->get( 'text_points' );
        $data['text_stock'] = $this->language->get( 'text_stock' );
        $data['text_discount'] = $this->language->get( 'text_discount' );
        $data['text_tax'] = $this->language->get( 'text_tax' );
        $data['text_option'] = $this->language->get( 'text_option' );
        $data['text_empty'] = $this->language->get( 'text_empty' );

        $data['text_write'] = $this->language->get( 'text_write' );
        $data['text_note'] = $this->language->get( 'text_note' );
        $data['text_payment_recurring'] = $this->language->get( 'text_payment_recurring' );
        $data['text_loading'] = $this->language->get( 'text_loading' );

        $data['entry_qty'] = $this->language->get( 'entry_qty' );
        $data['entry_name'] = $this->language->get( 'entry_name' );
        $data['entry_review'] = $this->language->get( 'entry_review' );
        $data['entry_rating'] = $this->language->get( 'entry_rating' );
        $data['entry_good'] = $this->language->get( 'entry_good' );
        $data['entry_bad'] = $this->language->get( 'entry_bad' );

        $data['button_continue'] = $this->language->get( 'button_continue' );
        $data['continue'] = HTTP_SERVER;

        $data['products'] = array();

        $this->load->model( 'tool/image' );
        $this->load->model( 'catalog/product' );
        //$results = $this->model_catalog_product->getProduct($product_id);
        $results = $this->model_module_after_checkout->afterCheckoutGetProductStatusOff( $product_id );
        if ( $results ) {

            if ( $results['image'] ) {
                $image = $this->model_tool_image->resize( $results['image'], $this->config->get( 'config_image_product_width' ), $this->config->get( 'config_image_product_height' ) );
            } else {
                $image = $this->model_tool_image->resize( 'placeholder.png', $this->config->get( 'config_image_product_width' ), $this->config->get( 'config_image_product_height' ) );
            }

            if ( ( $this->config->get( 'config_customer_price' ) && $this->customer->isLogged() ) || !$this->config->get( 'config_customer_price' ) ) {
                $price = $this->currency->format( $this->tax->calculate( $results['price'], $results['tax_class_id'], $this->config->get( 'config_tax' ) ) );
            } else {
                $price = false;
            }

            if ( (float)$results['special'] ) {
                $special = $this->currency->format( $this->tax->calculate( $results['special'], $results['tax_class_id'], $this->config->get( 'config_tax' ) ) );
            } else {
                $special = false;
            }

            if ( $this->config->get( 'config_tax' ) ) {
                $tax = $this->currency->format( (float)$results['special'] ? $results['special'] : $results['price'] );
            } else {
                $tax = false;
            }

            if ( $this->config->get( 'config_review_status' ) ) {
                $rating = (int)$results['rating'];
            } else {
                $rating = false;
            }

            $data['product'] = array(
                'product_id' => $results['product_id'],
                'thumb' => $image,
                'name' => $results['name'],
                'description' => utf8_substr( strip_tags( html_entity_decode( $results['description'], ENT_QUOTES, 'UTF-8' ) ), 0, $this->config->get( 'config_product_description_length' ) ) . '..',
                'price' => $price,
                'special' => $special,
                'tax' => $tax,
                'minimum' => $results['minimum'] > 0 ? $results['minimum'] : 1,
                'rating' => $rating,
                'href' => $this->url->link( 'product/product', '&product_id=' . $results['product_id'] ),
                'view' => $results['viewed']
            );

            $file = DIR_SYSTEM . 'library/local_script/after_checkout/template/product.tpl';
            if ( file_exists( $file ) ) {
                return $this->afterCheckoutTemplate( $file, $data );
            }

        }// end if results;
        return 'Not found !';
    }

    // Template: Bestseller
    private function afterCheckoutGetBestseller()
    {
        $limit = $this->config->get( "after_checkout_count_output_product" );
        $width = $this->config->get( "config_image_category_width" );
        $height = $this->config->get( "config_image_category_height" );

        $this->load->language( 'module/bestseller' );
        $data['heading_title'] = $this->language->get( 'heading_title' );
        $data['text_tax'] = $this->language->get( 'text_tax' );
        $data['button_cart'] = $this->language->get( 'button_cart' );
        $data['button_wishlist'] = $this->language->get( 'button_wishlist' );
        $data['button_compare'] = $this->language->get( 'button_compare' );
        $this->load->model( 'catalog/product' );
        $this->load->model( 'tool/image' );
        $data['products'] = array();

        $this->load->language( "module/after_checkout" );
        $button = $this->config->get( "after_checkout_button" );
        if ( isset( $button['add'] ) ) {
            $data['button_addons'] = $button['add'];
        } else {
            $data['button_addons'] = $this->language->get( 'button_addons' );
        }

        if ( isset( $button['continue'] ) ) {
            $data['after_checkout_button_finish'] = $button['continue'];
        } else {
            $data['after_checkout_button_finish'] = $this->language->get( 'after_checkout_button_finish' );
        }
        unset( $button );

        $data['after_checkout_themes'] = ( strlen( $this->config->get( "after_checkout_page_themes" ) ) > 0 ) ? $this->config->get( "after_checkout_page_themes" ) : 0; //themes
        $data['button_addons_success'] = $this->language->get( 'button_addons_success' );
        $data['button_addons_success_checked'] = $this->language->get( 'button_addons_success_checked' );

        $results = $this->model_catalog_product->getBestSellerProducts( $limit );
        if ( $results ) {
            foreach ( $results as $result ) {
                if ( $result['image'] ) {
                    $image = $this->model_tool_image->resize( $result['image'], $width, $height );
                } else {
                    $image = $this->model_tool_image->resize( 'placeholder.png', $width, $height );
                }

                if ( ( $this->config->get( 'config_customer_price' ) && $this->customer->isLogged() ) || !$this->config->get( 'config_customer_price' ) ) {
                    $price = $this->currency->format( $this->tax->calculate( $result['price'], $result['tax_class_id'], $this->config->get( 'config_tax' ) ) );
                } else {
                    $price = false;
                }

                if ( (float)$result['special'] ) {
                    $special = $this->currency->format( $this->tax->calculate( $result['special'], $result['tax_class_id'], $this->config->get( 'config_tax' ) ) );
                } else {
                    $special = false;
                }

                if ( $this->config->get( 'config_tax' ) ) {
                    $tax = $this->currency->format( (float)$result['special'] ? $result['special'] : $result['price'] );
                } else {
                    $tax = false;
                }

                if ( $this->config->get( 'config_review_status' ) ) {
                    $rating = $result['rating'];
                } else {
                    $rating = false;
                }

                $data['products'][] = array(
                    'product_id' => $result['product_id'],
                    'thumb' => $image,
                    'name' => $result['name'],
                    'description' => utf8_substr( strip_tags( html_entity_decode( $result['description'], ENT_QUOTES, 'UTF-8' ) ), 0, $this->config->get( 'config_product_description_length' ) ) . '..',
                    'price' => $price,
                    'special' => $special,
                    'tax' => $tax,
                    'rating' => $rating,
                    'href' => $this->url->link( 'product/product', 'product_id=' . $result['product_id'] ),
                    'view' => $result['viewed']
                );
            }

            //random array
            if ( $this->config->get( 'after_checkout_random_output_product' ) == 'on' ) {
                shuffle( $data['products'] );
            }

            $file = DIR_SYSTEM . 'library/local_script/after_checkout/template/bestseller.tpl';
            if ( file_exists( $file ) ) {
                return $this->afterCheckoutTemplate( $file, $data );
            }

        } else {
            return false;
        }

    }

    // Template: Special
    private function afterCheckoutGetSpecial()
    {
        $limit = $this->config->get( "after_checkout_count_output_product" );
        $width = $this->config->get( "config_image_category_width" );
        $height = $this->config->get( "config_image_category_height" );

        $this->load->language( 'module/special' );
        $data['heading_title'] = $this->language->get( 'heading_title' );
        $data['text_tax'] = $this->language->get( 'text_tax' );
        $data['button_cart'] = $this->language->get( 'button_cart' );
        $data['button_wishlist'] = $this->language->get( 'button_wishlist' );
        $data['button_compare'] = $this->language->get( 'button_compare' );

        $this->load->language( "module/after_checkout" );
        $button = $this->config->get( "after_checkout_button" );
        if ( isset( $button['add'] ) ) {
            $data['button_addons'] = $button['add'];
        } else {
            $data['button_addons'] = $this->language->get( 'button_addons' );
        }

        if ( isset( $button['continue'] ) ) {
            $data['after_checkout_button_finish'] = $button['continue'];
        } else {
            $data['after_checkout_button_finish'] = $this->language->get( 'after_checkout_button_finish' );
        }
        unset( $button );

        $data['after_checkout_themes'] = ( strlen( $this->config->get( "after_checkout_page_themes" ) ) > 0 ) ? $this->config->get( "after_checkout_page_themes" ) : 0; //themes
        $data['button_addons_success'] = $this->language->get( 'button_addons_success' );
        $data['button_addons_success_checked'] = $this->language->get( 'button_addons_success_checked' );

        $this->load->model( 'catalog/product' );
        $this->load->model( 'tool/image' );
        $data['products'] = array();
        $filter_data = array(
            'sort' => 'pd.name',
            'order' => 'ASC',
            'start' => 0,
            'limit' => $limit
        );

        $results = $this->model_catalog_product->getProductSpecials( $filter_data );
        if ( $results ) {
            foreach ( $results as $result ) {
                if ( $result['image'] ) {
                    $image = $this->model_tool_image->resize( $result['image'], $width, $height );
                } else {
                    $image = $this->model_tool_image->resize( 'placeholder.png', $width, $height );
                }

                if ( ( $this->config->get( 'config_customer_price' ) && $this->customer->isLogged() ) || !$this->config->get( 'config_customer_price' ) ) {
                    $price = $this->currency->format( $this->tax->calculate( $result['price'], $result['tax_class_id'], $this->config->get( 'config_tax' ) ) );
                } else {
                    $price = false;
                }

                if ( (float)$result['special'] ) {
                    $special = $this->currency->format( $this->tax->calculate( $result['special'], $result['tax_class_id'], $this->config->get( 'config_tax' ) ) );
                } else {
                    $special = false;
                }

                if ( $this->config->get( 'config_tax' ) ) {
                    $tax = $this->currency->format( (float)$result['special'] ? $result['special'] : $result['price'] );
                } else {
                    $tax = false;
                }

                if ( $this->config->get( 'config_review_status' ) ) {
                    $rating = $result['rating'];
                } else {
                    $rating = false;
                }

                $data['products'][] = array(
                    'product_id' => $result['product_id'],
                    'thumb' => $image,
                    'name' => $result['name'],
                    'description' => utf8_substr( strip_tags( html_entity_decode( $result['description'], ENT_QUOTES, 'UTF-8' ) ), 0, $this->config->get( 'config_product_description_length' ) ) . '..',
                    'price' => $price,
                    'special' => $special,
                    'tax' => $tax,
                    'rating' => $rating,
                    'href' => $this->url->link( 'product/product', 'product_id=' . $result['product_id'] ),
                    'view' => $result['viewed']
                );
            }

            //random array
            if ( $this->config->get( 'after_checkout_random_output_product' ) == 'on' ) {
                shuffle( $data['products'] );
            }


            $file = DIR_SYSTEM . 'library/local_script/after_checkout/template/special.tpl';
            if ( file_exists( $file ) ) {
                return $this->afterCheckoutTemplate( $file, $data );
            }

        } else {
            return false;
        }

    }

    // Template: Latest
    private function afterCheckoutGetLatest()
    {
        $limit = $this->config->get( "after_checkout_count_output_product" );
        $width = $this->config->get( "config_image_category_width" );
        $height = $this->config->get( "config_image_category_height" );

        $this->load->language( 'module/latest' );
        $data['heading_title'] = $this->language->get( 'heading_title' );
        $data['text_tax'] = $this->language->get( 'text_tax' );
        $data['button_cart'] = $this->language->get( 'button_cart' );
        $data['button_wishlist'] = $this->language->get( 'button_wishlist' );
        $data['button_compare'] = $this->language->get( 'button_compare' );

        $this->load->language( "module/after_checkout" );
        $button = $this->config->get( "after_checkout_button" );
        if ( isset( $button['add'] ) ) {
            $data['button_addons'] = $button['add'];
        } else {
            $data['button_addons'] = $this->language->get( 'button_addons' );
        }

        if ( isset( $button['continue'] ) ) {
            $data['after_checkout_button_finish'] = $button['continue'];
        } else {
            $data['after_checkout_button_finish'] = $this->language->get( 'after_checkout_button_finish' );
        }
        unset( $button );

        $data['after_checkout_themes'] = ( strlen( $this->config->get( "after_checkout_page_themes" ) ) > 0 ) ? $this->config->get( "after_checkout_page_themes" ) : 0; //themes
        $data['button_addons_success'] = $this->language->get( 'button_addons_success' );
        $data['button_addons_success_checked'] = $this->language->get( 'button_addons_success_checked' );

        $this->load->model( 'catalog/product' );
        $this->load->model( 'tool/image' );
        $data['products'] = array();

        $filter_data = array(
            'sort' => 'p.date_added',
            'order' => 'DESC',
            'start' => 0,
            'limit' => $limit
        );

        $results = $this->model_catalog_product->getProducts( $filter_data );
        if ( $results ) {
            foreach ( $results as $result ) {
                if ( $result['image'] ) {
                    $image = $this->model_tool_image->resize( $result['image'], $width, $height );
                } else {
                    $image = $this->model_tool_image->resize( 'placeholder.png', $width, $height );
                }

                if ( ( $this->config->get( 'config_customer_price' ) && $this->customer->isLogged() ) || !$this->config->get( 'config_customer_price' ) ) {
                    $price = $this->currency->format( $this->tax->calculate( $result['price'], $result['tax_class_id'], $this->config->get( 'config_tax' ) ) );
                } else {
                    $price = false;
                }

                if ( (float)$result['special'] ) {
                    $special = $this->currency->format( $this->tax->calculate( $result['special'], $result['tax_class_id'], $this->config->get( 'config_tax' ) ) );
                } else {
                    $special = false;
                }

                if ( $this->config->get( 'config_tax' ) ) {
                    $tax = $this->currency->format( (float)$result['special'] ? $result['special'] : $result['price'] );
                } else {
                    $tax = false;
                }

                if ( $this->config->get( 'config_review_status' ) ) {
                    $rating = $result['rating'];
                } else {
                    $rating = false;
                }

                $data['products'][] = array(
                    'product_id' => $result['product_id'],
                    'thumb' => $image,
                    'name' => $result['name'],
                    'description' => utf8_substr( strip_tags( html_entity_decode( $result['description'], ENT_QUOTES, 'UTF-8' ) ), 0, $this->config->get( 'config_product_description_length' ) ) . '..',
                    'price' => $price,
                    'special' => $special,
                    'tax' => $tax,
                    'rating' => $rating,
                    'href' => $this->url->link( 'product/product', 'product_id=' . $result['product_id'] ),
                    'view' => $result['viewed']
                );
            }

            //random array
            if ( $this->config->get( 'after_checkout_random_output_product' ) == 'on' ) {
                shuffle( $data['products'] );
            }

            $file = DIR_SYSTEM . 'library/local_script/after_checkout/template/latest.tpl';
            if ( file_exists( $file ) ) {
                return $this->afterCheckoutTemplate( $file, $data );
            }

            $file_template = DIR_TEMPLATE . $this->config->get( 'config_template' ) . '/template/module/latest.tpl';
            if ( file_exists( $file_template ) ) {
                return $this->load->view( $this->config->get( 'config_template' ) . '/template/module/latest.tpl', $data );
            } else {
                return $this->load->view( 'default/template/module/latest.tpl', $data );
            }

        } else {
            return false;
        }

    }

    // Template: Association
    private function afterCheckoutGetAssociations()
    {

        $last_order_id = $this->afterCheckoutGetLastOrderId();

        $data = array();
        $data['heading_title'] = $this->language->get( 'heading_title' );
        $data['text_tax'] = $this->language->get( 'text_tax' );
        $data['button_cart'] = $this->language->get( 'button_cart' );
        $data['button_wishlist'] = $this->language->get( 'button_wishlist' );
        $data['button_compare'] = $this->language->get( 'button_compare' );

        $data['after_checkout_themes'] = ( strlen( $this->config->get( "after_checkout_page_themes" ) ) > 0 ) ? $this->config->get( "after_checkout_page_themes" ) : 0; //themes
        $this->load->language( "module/after_checkout" );
        $button = $this->config->get( "after_checkout_button" );
        if ( isset( $button['add'] ) ) {
            $data['button_addons'] = $button['add'];
        } else {
            $data['button_addons'] = $this->language->get( 'button_addons' );
        }

        if ( isset( $button['continue'] ) ) {
            $data['after_checkout_button_finish'] = $button['continue'];
        } else {
            $data['after_checkout_button_finish'] = $this->language->get( 'after_checkout_button_finish' );
        }
        unset( $button );

        $data['button_addons_success'] = $this->language->get( 'button_addons_success' );
        $data['button_addons_success_checked'] = $this->language->get( 'button_addons_success_checked' );

        if ( $this->config->get( "after_checkout_count_output_product" ) ) {
            $limit = $this->config->get( "after_checkout_count_output_product" );
        } else {
            $limit = 10;
        }

        $data['products'] = $this->afterCheckoutGetAssociationsCategory( $last_order_id['order_categories'], $limit );

        if ( !$data['products'] ) {
            return false;
        }
        return $this->afterCheckoutTemplate( DIR_SYSTEM . 'library/local_script/after_checkout/template/associations.tpl', $data );

    }

    private function afterCheckoutGetAssociationsCategory( $category_id, $limit )
    {
        $data = array();

        $width = $this->config->get( "config_image_category_width" );
        $height = $this->config->get( "config_image_category_height" );

        $this->load->model( 'catalog/product' );
        $this->load->model( 'tool/image' );
        $products = array();
        $unique_products = array();

        if ( isset( $category_id ) ) {

            $l = 0; // идентификатор лимита для цикла
            foreach ( $category_id as $category ) {

                $results = null;
                $results = $this->model_catalog_product->getProducts( array(
                        'filter_category_id' => $category,
                        'sort' => 'p.date_added',
                        'start' => 0,
                        'limit' => $limit
                    )
                );

                if ( $results ) {

                    //random array
                    if ( $this->config->get( 'after_checkout_random_output_product' ) == 'on' ) {
                        shuffle( $results );
                    }

                    foreach ( $results as $result ) {

                        if ( $result['image'] ) {
                            $image = $this->model_tool_image->resize( $result['image'], $width, $height );
                        } else {
                            $image = $this->model_tool_image->resize( 'placeholder.png', $width, $height );
                        }

                        if ( ( $this->config->get( 'config_customer_price' ) && $this->customer->isLogged() ) || !$this->config->get( 'config_customer_price' ) ) {
                            $price = $this->currency->format( $this->tax->calculate( $result['price'], $result['tax_class_id'], $this->config->get( 'config_tax' ) ) );
                        } else {
                            $price = false;
                        }

                        if ( (float)$result['special'] ) {
                            $special = $this->currency->format( $this->tax->calculate( $result['special'], $result['tax_class_id'], $this->config->get( 'config_tax' ) ) );
                        } else {
                            $special = false;
                        }

                        if ( $this->config->get( 'config_tax' ) ) {
                            $tax = $this->currency->format( (float)$result['special'] ? $result['special'] : $result['price'] );
                        } else {
                            $tax = false;
                        }

                        if ( $this->config->get( 'config_review_status' ) ) {
                            $rating = $result['rating'];
                        } else {
                            $rating = false;
                        }

                        $products[] = array(
                            'product_id' => $result['product_id'],
                            'thumb' => $image,
                            'name' => $result['name'],
                            'description' => utf8_substr( strip_tags( html_entity_decode( $result['description'], ENT_QUOTES, 'UTF-8' ) ), 0, $this->config->get( 'config_product_description_length' ) ) . '..',
                            'price' => $price,
                            'special' => $special,
                            'tax' => $tax,
                            'rating' => $rating,
                            'href' => $this->url->link( 'product/product', 'product_id=' . $result['product_id'] ),
                            'view' => $result['viewed']
                        );


                    }

                    $unique_products = @array_unique( $products );

                } else {
                    return false;
                }

                if ( $l >= $limit ) {
                    break;
                }
                $l++;

            }
        }

        return $unique_products;
    }

    // View template
    private function afterCheckoutTemplate( $template, $data = array() )
    {
        $file = $template;

        if ( file_exists( $file ) ) {
            extract( $data );
            ob_start();
            require( $file );
            $output = ob_get_contents();
            ob_end_clean();
        } else {
            trigger_error( 'Error: Could not load template ' . $file . '!' );
            exit();
        }
        return $output;
    }

    // Format number
    private function ach_format( $num, $dec = 4, $dp = ".", $ts = "" )
    {
        return number_format( (float)$num, $dec, $dp, $ts );
    }


}