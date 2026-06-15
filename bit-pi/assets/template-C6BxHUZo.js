const e={data:{edges:[{id:"edge-1",selected:!1,source:"17-1",sourceHandle:"right",target:"17-2",targetHandle:"left"},{id:"edge-2",selected:!1,source:"17-2",sourceHandle:"right",target:"17-3",targetHandle:"left"}],nodes:[{connectable:!0,data:[],dragging:!1,height:70,id:"17-1",position:{x:117,y:110},positionAbsolute:{x:117,y:110},selected:!1,sourcePosition:"right",targetPosition:"left",type:"trigger",width:240},{connectable:!0,data:[],dragging:!1,height:70,id:"17-2",position:{x:497,y:110},positionAbsolute:{x:497,y:110},selected:!1,sourcePosition:"right",targetPosition:"left",type:"action",width:240},{connectable:!0,data:[],dragging:!1,height:70,id:"17-3",position:{x:877,y:110},positionAbsolute:{x:877,y:110},selected:!1,sourcePosition:"right",targetPosition:"left",type:"action",width:240},{connectable:!0,data:{color:"green",text:`## New Order

Starts when a new WooCommerce order is created.`},dragging:!1,height:150,id:"17-4",position:{x:126.95260663507099,y:-79.82227488151662},positionAbsolute:{x:126.95260663507099,y:-79.82227488151662},selected:!1,sourcePosition:"right",style:{height:150,width:220},targetPosition:"left",type:"stickyNote",width:220,zIndex:-1},{connectable:!0,data:{color:"purple",text:`## Order Value Check

Only continues when the order total is above the selected amount.`},dragging:!1,height:150,id:"17-5",position:{x:497.0947867298578,y:-90.24881516587678},positionAbsolute:{x:497.0947867298578,y:-90.24881516587678},selected:!1,sourcePosition:"right",style:{height:150,width:220},targetPosition:"left",type:"stickyNote",width:220,zIndex:-1},{connectable:!0,data:{color:"blue",text:`## Team Alert

Sends high-value order details to Telegram for quick review before fulfillment.`},dragging:!1,height:150,id:"17-6",position:{x:890.6966824644547,y:-90.24881516587679},positionAbsolute:{x:890.6966824644547,y:-90.24881516587679},selected:!1,sourcePosition:"right",style:{height:150,width:220},targetPosition:"left",type:"stickyNote",width:220,zIndex:-1}],viewport:{x:0,y:0,zoom:1}},map:{id:"17-1",next:{id:"17-2",next:{id:"17-3",previous:"17-2",type:"action"},previous:"17-1",type:"action"},previous:null,type:"trigger"},nodes:[{app_slug:"wooCommerce",data:{},field_mapping:{configs:{"hook-listener":{value:""}},data:[],repeaters:[]},machine_slug:"newOrderCreated",node_id:"17-1",variables:[{key:"order_id",type:"integer",value:140},{key:"order",type:"collection",value:[{key:"id",type:"integer",value:140},{key:"order_key",type:"string",value:"wc_order_1ZXSTQAYLI4kS"},{key:"card_tax",type:"string",value:"0"},{key:"currency",type:"string",value:"BDT"},{key:"discount_tax",type:"string",value:"0"},{key:"discount_to_display",type:"string",value:'<span class="woocommerce-Price-amount amount"><bdi>0.00<span class="woocommerce-Price-currencySymbol">&#2547;&nbsp;</span></bdi></span>'},{key:"discount_total",type:"string",value:"0"},{key:"fees",type:"collection",value:[]},{key:"shipping_tax",type:"string",value:"0"},{key:"shipping_total",type:"string",value:"0"},{key:"tax_totals",type:"collection",value:[]},{key:"total",type:"string",value:"35990.00"},{key:"total_refunded",type:"integer",value:0},{key:"total_tax_refunded",type:"double",value:0},{key:"total_shipping_refunded",type:"double",value:0},{key:"total_qty_refunded",type:"integer",value:0},{key:"remaining_refund_amount",type:"string",value:"35990.00"},{key:"shipping_method",type:"string",value:"Free shipping"},{key:"date_created",type:"string",value:"2026-05-22 12:02:28"},{key:"date_modified",type:"string",value:"2026-05-22 12:03:38"},{key:"date_completed",type:"NULL",value:null},{key:"date_paid",type:"NULL",value:null},{key:"customer_id",type:"integer",value:1},{key:"created_via",type:"string",value:"store-api"},{key:"billing_first_name",type:"string",value:"Arif"},{key:"billing_last_name",type:"string",value:"Hasnat"},{key:"billing_company",type:"string",value:""},{key:"billing_address_1",type:"string",value:"221B Backer Street"},{key:"billing_address_2",type:"string",value:"Consequatur Exercit"},{key:"billing_city",type:"string",value:"Chattogram"},{key:"billing_state",type:"string",value:"BD-01"},{key:"billing_postcode",type:"string",value:"4221"},{key:"billing_country",type:"string",value:"BD"},{key:"billing_email",type:"string",value:"cocewew@mailinator.com"},{key:"billing_phone",type:"string",value:"+1 (602) 891-7037"},{key:"shipping_first_name",type:"string",value:"Mehedi"},{key:"shipping_last_name",type:"string",value:"Miraz"},{key:"shipping_company",type:"string",value:""},{key:"shipping_address_1",type:"string",value:"Khulshi"},{key:"shipping_address_2",type:"string",value:""},{key:"shipping_city",type:"string",value:"Chattogram"},{key:"shipping_state",type:"string",value:"BD-10"},{key:"shipping_postcode",type:"string",value:"4221"},{key:"shipping_country",type:"string",value:"BD"},{key:"payment_method",type:"string",value:"cheque"},{key:"payment_method_title",type:"string",value:"Check payments"},{key:"status",type:"string",value:"pending"},{key:"checkout_order_received_url",type:"string",value:"https://arifhasnat.towp.io/checkout/order-received/140/?key=wc_order_1ZXSTQAYLI4kS"},{key:"customer_note",type:"string",value:"need this phone urgent"},{key:"_wc_order_attribution_referrer",type:"string",value:""},{key:"_wc_order_attribution_user_agent",type:"string",value:"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36"},{key:"_wc_order_attribution_utm_source",type:"string",value:"(direct)"},{key:"_wc_order_attribution_device_type",type:"string",value:"Desktop"},{key:"_wc_order_attribution_source_type",type:"string",value:"typein"},{key:"_wc_order_attribution_session_count",type:"string",value:"6"},{key:"_wc_order_attribution_session_entry",type:"string",value:"https://arifhasnat.towp.io/"},{key:"_wc_order_attribution_session_pages",type:"string",value:"6"},{key:"_wc_order_attribution_session_start_time",type:"string",value:"2026-05-22 04:51:15"},{key:"line_items",type:"array",value:[{key:0,type:"collection",value:[{key:"product_id",type:"integer",value:16},{key:"variation_id",type:"integer",value:0},{key:"product_name",type:"string",value:"Smart Phone"},{key:"quantity",type:"integer",value:1},{key:"subtotal",type:"string",value:"35990"},{key:"total",type:"string",value:"35990"},{key:"subtotal_tax",type:"string",value:"0"},{key:"tax_class",type:"string",value:""},{key:"tax_status",type:"string",value:"taxable"},{key:"product_sku",type:"string",value:"12000"},{key:"product_unit_price",type:"string",value:"35990"}]}]}]}]},{app_slug:"openAi",data:{db:{connectionId:18,messagesList:[[{name:"role",value:"user"},{name:"content",value:[{type:"string",value:"Analyze this WooCommerce order and write a short Telegram alert for the team."},{type:"string",value:`
`},{type:"string",value:"Order details:"},{type:"string",value:`
`},{type:"string",value:"Order ID: "},{dType:"integer",label:"1. order_id",mixInputId:"real-canyons-spend",nodeId:"17-1",path:"order_id",type:"variable"},{type:"string",value:`
`},{type:"string",value:"Customer Name: "},{dType:"string",label:"1. billing_first_name",mixInputId:"tired-mirrors-open",nodeId:"17-1",path:"order.billing_first_name",type:"variable"},{type:"string",value:" "},{dType:"string",label:"1. billing_last_name",mixInputId:"light-beers-find",nodeId:"17-1",path:"order.billing_last_name",type:"variable"},{type:"string",value:`
`},{type:"string",value:"Customer Email: "},{dType:"string",label:"1. billing_email",mixInputId:"soft-steaks-sit",nodeId:"17-1",path:"order.billing_email",type:"variable"},{type:"string",value:`
`},{type:"string",value:"Customer Phone: "},{dType:"string",label:"1. billing_phone",mixInputId:"major-pandas-thank",nodeId:"17-1",path:"order.billing_phone",type:"variable"},{type:"string",value:`
`},{type:"string",value:"Product: "},{dType:"string",label:"1. product_name",mixInputId:"clean-pens-kneel",nodeId:"17-1",path:"order.line_items.0.product_name",type:"variable"},{type:"string",value:`
`},{type:"string",value:"Quantity: "},{dType:"integer",label:"1. quantity",mixInputId:"eighty-mammals-kneel",nodeId:"17-1",path:"order.line_items.0.quantity",type:"variable"},{type:"string",value:`
`},{type:"string",value:"Total: "},{dType:"string",label:"1. total",mixInputId:"spicy-results-check",nodeId:"17-1",path:"order.total",type:"variable"},{type:"string",value:" "},{dType:"string",label:"1. currency",mixInputId:"metal-shirts-exist",nodeId:"17-1",path:"order.currency",type:"variable"},{type:"string",value:`
`},{type:"string",value:"Payment Method:"},{dType:"string",label:"1. payment_method",mixInputId:"early-schools-itch",nodeId:"17-1",path:"order.payment_method",type:"variable"},{type:"string",value:`
`},{type:"string",value:"Shipping Method: "},{dType:"string",label:"1. shipping_method",mixInputId:"fuzzy-wasps-invite",nodeId:"17-1",path:"order.shipping_method",type:"variable"},{type:"string",value:`
`},{type:"string",value:"Shipping Address: "},{dType:"string",label:"1. shipping_address_1",mixInputId:"smart-oranges-notice",nodeId:"17-1",path:"order.shipping_address_1",type:"variable"},{type:"string",value:" "},{dType:"string",label:"1. shipping_address_2",mixInputId:"nice-doors-cough",nodeId:"17-1",path:"order.shipping_address_2",type:"variable"},{type:"string",value:" "},{dType:"string",label:"1. shipping_city",mixInputId:"ripe-lights-pull",nodeId:"17-1",path:"order.shipping_city",type:"variable"},{type:"string",value:" "},{dType:"string",label:"1. shipping_state",mixInputId:"smooth-geese-pump",nodeId:"17-1",path:"order.shipping_state",type:"variable"},{type:"string",value:" "},{dType:"string",label:"1. shipping_postcode",mixInputId:"dull-feet-post",nodeId:"17-1",path:"order.shipping_postcode",type:"variable"},{type:"string",value:" "},{dType:"string",label:"1. shipping_country",mixInputId:"few-things-go",nodeId:"17-1",path:"order.shipping_country",type:"variable"},{type:"string",value:`
`},{type:"string",value:"Customer Note: "},{dType:"string",label:"1. customer_note",mixInputId:"cold-plants-matter",nodeId:"17-1",path:"order.customer_note",type:"variable"},{type:"string",value:`
`},{type:"string",value:"Priority rules:"},{type:"string",value:`
`},{type:"string",value:"- High priority if the order total is above 30000 BDT."},{type:"string",value:`
`},{type:"string",value:"- High priority if the customer note mentions urgent delivery, call before delivery, address issue, payment issue, cancellation, product change, refund request, wrong item, fragile handling, or special delivery timing."},{type:"string",value:`
`},{type:"string",value:"- High priority if the status is failed, on-hold, or pending with Cash on delivery."},{type:"string",value:`
`},{type:"string",value:"- If the order is not High priority, return nothing."},{type:"string",value:`
`},{type:"string",value:"Write the Telegram message only when the priority is High."},{type:"string",value:`
`},{type:"string",value:"Telegram message format:"},{type:"string",value:`
`},{type:"string",value:"High Priority WooCommerce Order Alert"},{type:"string",value:`
`},{type:"string",value:"Priority: High"},{type:"string",value:`
`},{type:"string",value:"Order:"},{type:"string",value:`
`},{type:"string",value:"Customer:"},{type:"string",value:`
`},{type:"string",value:"Phone:"},{type:"string",value:`
`},{type:"string",value:"Product:"},{type:"string",value:`
`},{type:"string",value:"Quantity:"},{type:"string",value:`
`},{type:"string",value:"Total:"},{type:"string",value:`
`},{type:"string",value:"Payment:"},{type:"string",value:`
`},{type:"string",value:"Status:"},{type:"string",value:`
`},{type:"string",value:"Shipping:"},{type:"string",value:`
`},{type:"string",value:"Address:"},{type:"string",value:`
`},{type:"string",value:"Attention:"},{type:"string",value:`
`},{type:"string",value:"Next action:"},{type:"string",value:`
`},{type:"string",value:"Rules:"},{type:"string",value:`
`},{type:"string",value:"- Return the Telegram message only if the priority is High."},{type:"string",value:`
`},{type:"string",value:"- If the priority is Medium or Normal, return an empty response."},{type:"string",value:`
`},{type:"string",value:"- Do not return JSON."},{type:"string",value:`
`},{type:"string",value:"- Do not add explanation."},{type:"string",value:`
`},{type:"string",value:"- Keep it short and easy to scan."},{type:"string",value:`
`},{type:"string",value:"- Write for the internal team, not the customer."},{type:"string",value:`
`},{type:"string",value:"- If payment method is Cash on delivery, write “COD order” in the Payment line."},{type:"string",value:`
`},{type:"string",value:"- If Customer Note has an urgent issue, explain the emergency in the Attention line."},{type:"string",value:`
`},{type:"string",value:"- If the order is high priority because of amount, write “High-value order needs review” in the Attention line."},{type:"string",value:`
`},{type:"string",value:"- If the order is high priority because of status, write what status needs review."},{type:"string",value:`
`},{type:"string",value:"- In Next action, tell the team what to check before fulfillment."}]}]],model:"gpt-5.4"}},field_mapping:{configs:{"connection-id":{value:18},"context-length":{value:[]},"memory-key":{value:[]},"memory-key-switch":{value:!1}},data:[{path:"model",value:"gpt-5.4"},{path:"max_tokens",value:[]},{path:"advance-feature",value:!1},{path:"response_format",value:"text"},{path:"reasoning_effort",value:null},{path:"temperature",value:[]},{path:"top_p",value:[]},{path:"n",value:[]},{path:"frequency_penalty",value:[]},{path:"presence_penalty",value:[]},{path:"seed",value:[]}],repeaters:{"messages-list":{value:[[{key:"role",value:"user"},{key:"content",value:[{type:"string",value:"Analyze this WooCommerce order and write a short Telegram alert for the team."},{type:"string",value:`
`},{type:"string",value:"Order details:"},{type:"string",value:`
`},{type:"string",value:"Order ID: "},{dType:"integer",label:"1. order_id",mixInputId:"real-canyons-spend",nodeId:"17-1",path:"order_id",type:"variable"},{type:"string",value:`
`},{type:"string",value:"Customer Name: "},{dType:"string",label:"1. billing_first_name",mixInputId:"tired-mirrors-open",nodeId:"17-1",path:"order.billing_first_name",type:"variable"},{type:"string",value:" "},{dType:"string",label:"1. billing_last_name",mixInputId:"light-beers-find",nodeId:"17-1",path:"order.billing_last_name",type:"variable"},{type:"string",value:`
`},{type:"string",value:"Customer Email: "},{dType:"string",label:"1. billing_email",mixInputId:"soft-steaks-sit",nodeId:"17-1",path:"order.billing_email",type:"variable"},{type:"string",value:`
`},{type:"string",value:"Customer Phone: "},{dType:"string",label:"1. billing_phone",mixInputId:"major-pandas-thank",nodeId:"17-1",path:"order.billing_phone",type:"variable"},{type:"string",value:`
`},{type:"string",value:"Product: "},{dType:"string",label:"1. product_name",mixInputId:"clean-pens-kneel",nodeId:"17-1",path:"order.line_items.0.product_name",type:"variable"},{type:"string",value:`
`},{type:"string",value:"Quantity: "},{dType:"integer",label:"1. quantity",mixInputId:"eighty-mammals-kneel",nodeId:"17-1",path:"order.line_items.0.quantity",type:"variable"},{type:"string",value:`
`},{type:"string",value:"Total: "},{dType:"string",label:"1. total",mixInputId:"spicy-results-check",nodeId:"17-1",path:"order.total",type:"variable"},{type:"string",value:" "},{dType:"string",label:"1. currency",mixInputId:"metal-shirts-exist",nodeId:"17-1",path:"order.currency",type:"variable"},{type:"string",value:`
`},{type:"string",value:"Payment Method:"},{dType:"string",label:"1. payment_method",mixInputId:"early-schools-itch",nodeId:"17-1",path:"order.payment_method",type:"variable"},{type:"string",value:`
`},{type:"string",value:"Shipping Method: "},{dType:"string",label:"1. shipping_method",mixInputId:"fuzzy-wasps-invite",nodeId:"17-1",path:"order.shipping_method",type:"variable"},{type:"string",value:`
`},{type:"string",value:"Shipping Address: "},{dType:"string",label:"1. shipping_address_1",mixInputId:"smart-oranges-notice",nodeId:"17-1",path:"order.shipping_address_1",type:"variable"},{type:"string",value:" "},{dType:"string",label:"1. shipping_address_2",mixInputId:"nice-doors-cough",nodeId:"17-1",path:"order.shipping_address_2",type:"variable"},{type:"string",value:" "},{dType:"string",label:"1. shipping_city",mixInputId:"ripe-lights-pull",nodeId:"17-1",path:"order.shipping_city",type:"variable"},{type:"string",value:" "},{dType:"string",label:"1. shipping_state",mixInputId:"smooth-geese-pump",nodeId:"17-1",path:"order.shipping_state",type:"variable"},{type:"string",value:" "},{dType:"string",label:"1. shipping_postcode",mixInputId:"dull-feet-post",nodeId:"17-1",path:"order.shipping_postcode",type:"variable"},{type:"string",value:" "},{dType:"string",label:"1. shipping_country",mixInputId:"few-things-go",nodeId:"17-1",path:"order.shipping_country",type:"variable"},{type:"string",value:`
`},{type:"string",value:"Customer Note: "},{dType:"string",label:"1. customer_note",mixInputId:"cold-plants-matter",nodeId:"17-1",path:"order.customer_note",type:"variable"},{type:"string",value:`
`},{type:"string",value:"Priority rules:"},{type:"string",value:`
`},{type:"string",value:"- High priority if the order total is above 30000 BDT."},{type:"string",value:`
`},{type:"string",value:"- High priority if the customer note mentions urgent delivery, call before delivery, address issue, payment issue, cancellation, product change, refund request, wrong item, fragile handling, or special delivery timing."},{type:"string",value:`
`},{type:"string",value:"- High priority if the status is failed, on-hold, or pending with Cash on delivery."},{type:"string",value:`
`},{type:"string",value:"- If the order is not High priority, return nothing."},{type:"string",value:`
`},{type:"string",value:"Write the Telegram message only when the priority is High."},{type:"string",value:`
`},{type:"string",value:"Telegram message format:"},{type:"string",value:`
`},{type:"string",value:"High Priority WooCommerce Order Alert"},{type:"string",value:`
`},{type:"string",value:"Priority: High"},{type:"string",value:`
`},{type:"string",value:"Order:"},{type:"string",value:`
`},{type:"string",value:"Customer:"},{type:"string",value:`
`},{type:"string",value:"Phone:"},{type:"string",value:`
`},{type:"string",value:"Product:"},{type:"string",value:`
`},{type:"string",value:"Quantity:"},{type:"string",value:`
`},{type:"string",value:"Total:"},{type:"string",value:`
`},{type:"string",value:"Payment:"},{type:"string",value:`
`},{type:"string",value:"Status:"},{type:"string",value:`
`},{type:"string",value:"Shipping:"},{type:"string",value:`
`},{type:"string",value:"Address:"},{type:"string",value:`
`},{type:"string",value:"Attention:"},{type:"string",value:`
`},{type:"string",value:"Next action:"},{type:"string",value:`
`},{type:"string",value:"Rules:"},{type:"string",value:`
`},{type:"string",value:"- Return the Telegram message only if the priority is High."},{type:"string",value:`
`},{type:"string",value:"- If the priority is Medium or Normal, return an empty response."},{type:"string",value:`
`},{type:"string",value:"- Do not return JSON."},{type:"string",value:`
`},{type:"string",value:"- Do not add explanation."},{type:"string",value:`
`},{type:"string",value:"- Keep it short and easy to scan."},{type:"string",value:`
`},{type:"string",value:"- Write for the internal team, not the customer."},{type:"string",value:`
`},{type:"string",value:"- If payment method is Cash on delivery, write “COD order” in the Payment line."},{type:"string",value:`
`},{type:"string",value:"- If Customer Note has an urgent issue, explain the emergency in the Attention line."},{type:"string",value:`
`},{type:"string",value:"- If the order is high priority because of amount, write “High-value order needs review” in the Attention line."},{type:"string",value:`
`},{type:"string",value:"- If the order is high priority because of status, write what status needs review."},{type:"string",value:`
`},{type:"string",value:"- In Next action, tell the team what to check before fulfillment."}]}]]},"optional-field-list":{path:"optionalFields",value:[]},"stop-sequences-list":{value:[]}}},machine_slug:"createCompletion",node_id:"17-2",variables:[{key:"id",type:"string",value:"chatcmpl-DjH3ERuYob7BpjUVjfmKi264LONTF"},{key:"object",type:"string",value:"chat.completion"},{key:"created",type:"integer",value:1779682676},{key:"model",type:"string",value:"gpt-5.4-2026-03-05"},{key:"choices",type:"array",value:[{key:0,type:"collection",value:[{key:"index",type:"integer",value:0},{key:"message",type:"collection",value:[{key:"role",type:"string",value:"assistant"},{key:"content",type:"string",value:`High Priority WooCommerce Order Alert
Priority: High
Order: #140
Customer: Arif Hasnat
Phone: +1 (602) 891-7037
Product: Smart Phone
Quantity: 1
Total: 35990.00 BDT
Payment: cheque
Status: Not provided
Shipping: Free shipping
Address: Khulshi, Chattogram, BD-10 4221, BD
Attention: Urgent delivery requested in customer note. High-value order needs review.
Next action: Call customer to confirm urgency, verify address and payment details, then review order before fulfillment.`},{key:"refusal",type:"NULL",value:null},{key:"annotations",type:"collection",value:[]}]},{key:"finish_reason",type:"string",value:"stop"}]}]},{key:"usage",type:"collection",value:[{key:"prompt_tokens",type:"integer",value:413},{key:"completion_tokens",type:"integer",value:129},{key:"total_tokens",type:"integer",value:542},{key:"prompt_tokens_details",type:"collection",value:[{key:"cached_tokens",type:"integer",value:0},{key:"audio_tokens",type:"integer",value:0}]},{key:"completion_tokens_details",type:"collection",value:[{key:"reasoning_tokens",type:"integer",value:0},{key:"audio_tokens",type:"integer",value:0},{key:"accepted_prediction_tokens",type:"integer",value:0},{key:"rejected_prediction_tokens",type:"integer",value:0}]}]},{key:"service_tier",type:"string",value:"default"},{key:"system_fingerprint",type:"NULL",value:null}]},{app_slug:"telegram",data:{db:{chat:[{type:"string",value:"-1003718289902"}],connection:23,text:[{dType:"string",label:"2. content",mixInputId:"clever-pandas-cough",nodeId:"17-2",path:"choices.0.message.content",type:"variable"}]}},field_mapping:{configs:{"connection-id":{value:23},"message-reply":{path:"advance-feature",value:"false"}},data:[{path:"chat_id",value:[{type:"string",value:"-1003718289902"}]},{path:"text",value:[{dType:"string",label:"2. content",mixInputId:"clever-pandas-cough",nodeId:"17-2",path:"choices.0.message.content",type:"variable"}]},{path:"message_thread_id",value:[]},{path:"parse_mode",value:"HTML"},{path:"disable_notification",value:"true"},{path:"disable_web_page_preview",value:"true"},{path:"protect_content",value:"false"},{path:"reply_to_message_id",value:[]}],repeaters:{"reply-markup":{path:"data",value:[]}}},machine_slug:"sendOrReplyMessage",node_id:"17-3",variables:[{key:"ok",type:"boolean",value:!0},{key:"result",type:"collection",value:[{key:"message_id",type:"integer",value:83},{key:"from",type:"collection",value:[{key:"id",type:"integer",value:8684122048},{key:"is_bot",type:"boolean",value:!0},{key:"first_name",type:"string",value:"Tele"},{key:"username",type:"string",value:"Tele_Tule_bot"}]},{key:"chat",type:"collection",value:[{key:"id",type:"integer",value:-0xe9b245a9ee},{key:"title",type:"string",value:"Bit"},{key:"type",type:"string",value:"supergroup"}]},{key:"date",type:"integer",value:1779682704},{key:"text",type:"string",value:`High Priority WooCommerce Order Alert
Priority: High
Order: #140
Customer: Arif Hasnat
Phone: +1 (602) 891-7037
Product: Smart Phone
Quantity: 1
Total: 35990.00 BDT
Payment: cheque
Status: Not provided
Shipping: Free shipping
Address: Khulshi, Chattogram, BD-10 4221, BD
Attention: Urgent delivery requested in customer note. High-value order needs review.
Next action: Call customer to confirm urgency, verify address and payment details, then review order before fulfillment.`}]}]}]};export{e as default};
