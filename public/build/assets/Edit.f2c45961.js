import{g as c,T as B,Q as ae,h as re,j as D,f as a,a as i,u as _,w as p,F as O,o as l,Z as ne,b as e,d as m,t as u,l as n,e as g,i as de,n as f,k as q,c as x,O as k}from"./app.95ed68ca.js";import{_ as ie}from"./Authenticated.ea8314f0.js";import{_ as y}from"./Button.9136e930.js";import ce from"./ChannelOverview.915cb7dc.js";import ue from"./EditItem.d551e722.js";import{_ as h}from"./FormInput.01719747.js";import{_ as A}from"./MultiSelect.bfa4b0b4.js";import{r as pe}from"./ArrowUturnLeftIcon.fcced71c.js";import{r as I}from"./CheckCircleIcon.f733c13b.js";import{r as T}from"./PlusCircleIcon.4851b7da.js";import{r as me}from"./PencilSquareIcon.ddcbd1c2.js";import{r as Q}from"./PauseCircleIcon.ee9b20ba.js";import{r as _e}from"./PlayCircleIcon.d4b919b8.js";import{r as ve}from"./XCircleIcon.36d2b507.js";import"./keyboard.fabbb910.js";import"./use-resolve-button-type.33acd29b.js";import"./RectangleStackIcon.5d9f6c7f.js";import"./Modal.f7f4d0c9.js";import"./disposables.e59e3e4d.js";import"./BackspaceIcon.073a5961.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.92f173fc.js";const fe={class:"font-semibold text-xl text-gray-800 leading-tight"},xe={key:0},ye={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},ge={class:"mt-6 flex flex-col"},he={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},be={class:"shadow-sm ring-1 ring-black ring-opacity-5 p-5"},we={class:"grid grid-cols-1 gap-3 sm:grid-cols-6 pb-2"},ke={class:"sm:col-span-6"},Pe={class:"sm:col-span-6"},Se=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Operator ",-1),Ve={class:"mt-1"},Ce=["value"],Me={class:"sm:col-span-6"},Oe=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Delivery Platform ",-1),je={class:"mt-1"},$e=["value"],Be={class:"sm:col-span-6"},De=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Platform Category ",-1),qe={class:"mt-1"},Ae=["value"],Ue={class:"sm:col-span-6"},Ee=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Refer to Product Mapping ",-1),Ne={class:"mt-1"},Re=["value"],Fe={class:"sm:col-span-3"},Ie={class:"sm:col-span-3"},Te=e("div",{class:"sm:col-span-6"},[e("label",{for:"reserved",class:"italic text-blue-800"},' By setting "Reserved Percentage" and "Reserved Quantity", the sellable qty equivalent to whichever higher. If lower than reserved, channel becomes inactive, both default value are 0. ')],-1),Qe={class:"sm:col-span-6"},Le={class:"flex space-x-1 mt-5 justify-end"},ze=e("span",null," Back ",-1),Ke=e("span",null," Save ",-1),Ze=e("div",{class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},[e("div",{class:"relative"},[e("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[e("div",{class:"w-full border-t border-gray-300"})]),e("div",{class:"relative flex justify-center"},[e("span",{class:"px-3 bg-white text-lg font-medium text-gray-900 rounded"},"Delivery Platform Product(s) ")])])],-1),Ge={key:0},He={key:1},Je=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Product ",-1),We={key:0,class:"text-sm text-red-600"},Xe={key:2},Ye={key:3},et=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Platform SubCategory ",-1),tt={key:4,class:"sm:col-span-1"},st=e("span",null," Add ",-1),ot={key:5,class:"sm:col-span-6 flex flex-col mt-3"},lt={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},at={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},rt={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},nt={class:"table-fixed min-w-full divide-y divide-gray-300"},dt=e("thead",{class:"bg-gray-50"},[e("tr",null,[e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," # "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Channel ID "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Thumbnail "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Product "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Status "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Price "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Platform SubCategory "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Action ")])],-1),it={class:"bg-white"},ct={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},ut={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},pt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},mt={class:"flex justify-center"},_t=["src"],vt={class:"whitespace-normal py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left flex flex-col"},ft={key:0},xt={key:1,class:"break-words"},yt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},gt={key:0,class:"inline-flex items-center rounded-md bg-green-300 px-1.5 py-0.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-indigo-700/10"},ht={key:1,class:"inline-flex items-center rounded-md bg-red-200 px-1.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-indigo-700/10"},bt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},wt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},kt={class:"whitespace-nowrap py-4 pr-2 text-sm text-center"},Pt=e("span",{class:"text-xs"}," Edit ",-1),St={key:0},Vt=e("td",{colspan:"7",class:"whitespace-nowrap py-4 text-sm font-medium text-gray-600 text-center"}," No Records Found ",-1),Ct=[Vt],Mt={key:6,class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},Ot=e("div",{class:"relative"},[e("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[e("div",{class:"w-full border-t border-gray-300"})]),e("div",{class:"relative flex justify-center"},[e("span",{class:"px-3 bg-white text-lg font-medium text-gray-900 rounded"}," Vending Machine Binding ")])],-1),jt=[Ot],$t={key:7,class:"sm:col-span-3"},Bt=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Vending Machine ",-1),Dt={key:0,class:"text-sm text-red-600"},qt={key:8,class:"sm:col-span-2"},At={key:9,class:"sm:col-span-1"},Ut=e("span",null," Add ",-1),Et={key:10,class:"sm:col-span-6 flex flex-col mt-3"},Nt={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},Rt={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},Ft={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},It={class:"min-w-full divide-y divide-gray-300"},Tt=e("thead",{class:"bg-gray-50"},[e("tr",null,[e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," # "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"},[m(" Vend ID "),e("br"),m(" (Platform Ref ID) ")]),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Vend Name "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Channel Status "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," VM Status "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Action ")])],-1),Qt={class:"bg-white"},Lt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},zt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},Kt=e("br",null,null,-1),Zt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left"},Gt={key:0},Ht=e("br",null,null,-1),Jt={key:1},Wt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},Xt=["onClick"],Yt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},es={key:0,class:"inline-flex items-center rounded-md bg-green-300 px-1.5 py-0.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-indigo-700/10"},ts={key:1,class:"inline-flex items-center rounded-md bg-red-200 px-1.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-indigo-700/10"},ss={class:"whitespace-nowrap py-2 text-xs text-center p-3"},os={class:"flex flex-col space-y-1"},ls={key:2,class:"text-xs"},as={key:3,class:"text-xs"},rs=e("span",{class:"text-xs"},"Unbind VM",-1),ns={key:0},ds=e("td",{colspan:"6",class:"whitespace-nowrap py-4 text-sm font-medium text-gray-600 text-center"}," No Records Found ",-1),is=[ds],$s={__name:"Edit",props:{bundleSalesOptions:[Array,Object],deliveryProductMapping:Object,productMappingItems:Object,productOptions:Object,type:String,unbindedVendOptions:[Array,Object]},setup(U){const d=U,E=c(""),b=c([]),L=c([]),P=c([]),z=c([]),N=c([]),K=c([]),t=c(B(F()));c(!1);const S=c(!0),Z=c([]),G=c([]),V=c(!1),C=c(!1),H=c([]),j=c("");ae().props.auth.roles;const R=c(),$=c([]);re(()=>{d.type=="create"?j.value="Create New":j.value="Edit",L.value=[...d.bundleSalesOptions.map(o=>({id:o.id,name:o.name,is_same:o.is_same,type:o.type,phrase_1:o.phrase_1,phrase_2:o.phrase_2,phrase_3:o.phrase_3}))],P.value=d.deliveryProductMapping.data,z.value=d.deliveryProductMapping.data.deliveryProductMappingBulks,K.value=d.deliveryProductMapping.data.deliveryProductMappingItems.map(o=>({id:o.id,full_name:"(#"+o.channel_code+") "+o.product.code+" "+o.product.name,img_url:o.product.thumbnail.full_url,amount:o.amount})),t.value=P.value?B(P.value):B(F()),$.value=[...d.unbindedVendOptions.data.map(o=>({id:o.id,full_name:o.full_name}))]}),D(function(){return t.value.promo_label&&t.value.promo_value&&t.value.total_qty?t.value.promo_label.phrase_1+t.value.total_qty+t.value.promo_label.phrase_2+t.value.promo_value+t.value.promo_label.phrase_3:""}),D(function(){let o=!0;return E.value="",(!t.value.promo_label||!t.value.promo_value||!t.value.delivery_product_mapping_item_id)&&(o=!1),t.value.total_qty==0&&(o=!1),S.value&&b.value.length==1&&(o=!1),!S.value&&b.value.length>0&&b.value.filter(r=>t.value.delivery_product_mapping_item_id.id==r.delivery_product_mapping_item_id.id).length>0&&(o=!1,E.value="Product already added"),o}),D(function(){let o=!1;return b.value.length>1&&!S.value&&(o=!0),b.value.length==1&&S.value&&(o=!0),o});function F(){return{id:"",bundle_name:"",category_json:"",delivery_platform_operator_id:"",name:"",operator_id:"",platform_ref_id:"",product_mapping_id:"",promo_label:"",promo_type:"",promo_value:"",reserved_percent:0,reserved_qty:0,sub_category_json:"",total_amount:"",total_qty:""}}function J(){Z.value.map(function(o){return o.channel_code}).indexOf(t.value.channel_code)<0&&k.post("/delivery-product-mapping-items/delivery-product-mapping/"+t.value.id+"/store",{...t.value,product_id:t.value.product_id?t.value.product_id.id:null},{preserveState:!1,preserveScroll:!0,replace:!0})}function W(o){k.post("/delivery-product-mappings/"+t.value.id+"/bind-vend",{vend_id:o,platform_ref_id:t.value.platform_ref_id},{preserveState:!1,preserveScroll:!0,replace:!0})}function X(o){R.value=o,V.value=!0}function Y(){V.value=!1}function ee(o){N.value=o,C.value=!0}function te(){C.value=!1}function se(){t.value.clearErrors(),t.value.transform(o=>({name:o.name,reserved_percent:o.reserved_percent,reserved_qty:o.reserved_qty})).post("/delivery-product-mappings/"+t.value.id+"/update",{preserveState:!0,replace:!0})}function oe(o){let r=o.is_active?"Are you sure to pause this vending machine?":"Are you sure to resume this vending machine?";!confirm(r)||k.post("/delivery-product-mappings/vends/"+o.id+"/toggle-pause-vend",{},{preserveState:!1,preserveScroll:!0,replace:!0})}function le(o){!confirm("Are you sure to delete this entry?")||k.delete("/delivery-product-mappings/unbind/"+o,{preserveState:!1,preserveScroll:!0,replace:!0,onSuccess:()=>{k.reload({only:["unbindedVendOptions"]}),$.value=d.unbindedVendOptions?d.unbindedVendOptions.data:[]}})}return(o,r)=>(l(),a(O,null,[i(_(ne),{title:"Delivery Product Mapping"}),i(ie,null,{header:p(()=>[e("h2",fe,[m(u(j.value)+" Delivery Product Mapping ",1),U.type=="update"?(l(),a("span",xe,u(P.value.name),1)):n("",!0)])]),default:p(()=>[e("div",ye,[e("div",ge,[e("div",he,[e("div",be,[e("form",{onSubmit:g(se,["prevent"]),id:"submit"},[e("div",we,[e("div",ke,[i(h,{modelValue:t.value.name,"onUpdate:modelValue":r[0]||(r[0]=s=>t.value.name=s),error:t.value.errors.name},{default:p(()=>[m(" Name ")]),_:1},8,["modelValue","error"])]),e("div",Pe,[Se,e("div",Ve,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:d.deliveryProductMapping.data.operator.full_name,disabled:""},null,8,Ce)])]),e("div",Me,[Oe,e("div",je,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:d.deliveryProductMapping.data.deliveryPlatformOperator.deliveryPlatform.name+" ("+d.deliveryProductMapping.data.deliveryPlatformOperator.type+")",disabled:""},null,8,$e)])]),e("div",Be,[De,e("div",qe,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:d.deliveryProductMapping.data.category_json.id+" - "+d.deliveryProductMapping.data.category_json.name,disabled:""},null,8,Ae)])]),e("div",Ue,[Ee,e("div",Ne,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:d.deliveryProductMapping.data.productMapping.name,disabled:""},null,8,Re)])]),e("div",Fe,[i(h,{modelValue:t.value.reserved_percent,"onUpdate:modelValue":r[1]||(r[1]=s=>t.value.reserved_percent=s),error:t.value.errors.reserved_percent},{default:p(()=>[m(" Reserved Percentage (%) ")]),_:1},8,["modelValue","error"])]),e("div",Ie,[i(h,{modelValue:t.value.reserved_qty,"onUpdate:modelValue":r[2]||(r[2]=s=>t.value.reserved_qty=s),error:t.value.errors.reserved_qty},{default:p(()=>[m(" Reserved Quantity ")]),_:1},8,["modelValue","error"])]),Te,e("div",Qe,[e("div",Le,[i(_(de),{href:"/delivery-product-mappings"},{default:p(()=>[i(y,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"},{default:p(()=>[i(_(pe),{class:"w-4 h-4"}),ze]),_:1})]),_:1}),i(y,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:p(()=>[i(_(I),{class:"w-4 h-4"}),Ke]),_:1})])]),Ze,t.value.id?(l(),a("div",Ge,[i(h,{modelValue:t.value.channel_code,"onUpdate:modelValue":r[3]||(r[3]=s=>t.value.channel_code=s),error:t.value.errors.channel_code,placeholderStr:"Channel ID"},{default:p(()=>[m(" Channel ID ")]),_:1},8,["modelValue","error"])])):n("",!0),t.value.id?(l(),a("div",He,[Je,i(A,{modelValue:t.value.product_id,"onUpdate:modelValue":r[4]||(r[4]=s=>t.value.product_id=s),options:G.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),t.value.errors.product_id?(l(),a("div",We,u(t.value.errors.product_id),1)):n("",!0)])):n("",!0),t.value.id?(l(),a("div",Xe,[i(h,{modelValue:t.value.amount,"onUpdate:modelValue":r[5]||(r[5]=s=>t.value.amount=s),error:t.value.errors.amount,placeholderStr:"Platform Price"},{default:p(()=>[m(" Price ")]),_:1},8,["modelValue","error"])])):n("",!0),t.value.id?(l(),a("div",Ye,[et,i(A,{modelValue:t.value.sub_category_json,"onUpdate:modelValue":r[6]||(r[6]=s=>t.value.sub_category_json=s),options:H.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):n("",!0),t.value.product_mapping_id?(l(),a("div",tt,[i(y,{type:"button",onClick:r[7]||(r[7]=g(s=>J(),["prevent"])),class:f(["bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6",[!t.value.channel_code||!t.value.product_id?"opacity-50 cursor-not-allowed":""]]),disabled:!t.value.channel_code||!t.value.product_id},{default:p(()=>[i(_(T),{class:"w-4 h-4"}),st]),_:1},8,["class","disabled"])])):n("",!0),t.value.product_mapping_id?(l(),a("div",ot,[e("div",lt,[e("div",at,[e("div",rt,[e("table",nt,[dt,e("tbody",it,[(l(!0),a(O,null,q(d.deliveryProductMapping.data.deliveryProductMappingItems,(s,w)=>(l(),a("tr",{key:s.id,class:f(w%2===0?void 0:"bg-gray-50")},[e("td",ct,u(w+1),1),e("td",ut,u(s.channel_code),1),e("td",pt,[e("div",mt,[s.product&&s.product.thumbnail?(l(),a("img",{key:0,class:"h-24 w-24 md:h-20 md:w-20 rounded-full",src:s.product.thumbnail.full_url,alt:""},null,8,_t)):n("",!0)])]),e("td",vt,[s.product.code?(l(),a("span",ft,u(s.product.code),1)):n("",!0),s.product.name?(l(),a("span",xt,u(s.product.name),1)):n("",!0)]),e("td",yt,[s.is_active==1?(l(),a("span",gt," Active ")):n("",!0),s.is_active==0?(l(),a("span",ht," Paused ")):n("",!0)]),e("td",bt,u(s.amount.toLocaleString(void 0,{minimumFractionDigits:d.deliveryProductMapping.data.operator.country.is_currency_exponent_hidden?0:d.deliveryProductMapping.data.operator.country.currency_exponent,maximumFractionDigits:d.deliveryProductMapping.data.operator.country.is_currency_exponent_hidden?0:d.deliveryProductMapping.data.operator.country.currency_exponent})),1),e("td",wt,u(s.sub_category_json.name),1),e("td",kt,[i(y,{class:"bg-gray-300 hover:bg-gray-400 text-black flex space-x-1",onClick:g(v=>ee(s),["prevent"])},{default:p(()=>[i(_(me),{class:"w-3 h-3"}),Pt]),_:2},1032,["onClick"])])],2))),128)),!d.deliveryProductMapping.data.deliveryProductMappingItems||!d.deliveryProductMapping.data.deliveryProductMappingItems.length?(l(),a("tr",St,Ct)):n("",!0)])])])])])])):n("",!0),t.value.product_mapping_id?(l(),a("div",Mt,jt)):n("",!0),t.value.product_mapping_id?(l(),a("div",$t,[Bt,i(A,{modelValue:t.value.vend_id,"onUpdate:modelValue":r[8]||(r[8]=s=>t.value.vend_id=s),options:$.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),t.value.errors.vend_id?(l(),a("div",Dt,u(t.value.errors.vend_id),1)):n("",!0)])):n("",!0),t.value.product_mapping_id?(l(),a("div",qt,[i(h,{modelValue:t.value.platform_ref_id,"onUpdate:modelValue":r[9]||(r[9]=s=>t.value.platform_ref_id=s),error:t.value.errors.platform_ref_id,placeholderStr:"Platform ID"},{default:p(()=>[m(" Platform ID (Store ID) ")]),_:1},8,["modelValue","error"])])):n("",!0),t.value.product_mapping_id?(l(),a("div",At,[i(y,{type:"button",onClick:r[10]||(r[10]=g(s=>W(t.value.vend_id.id),["prevent"])),class:f(["bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6",[t.value.vend_id?"":"opacity-50 cursor-not-allowed"]]),disabled:!t.value.vend_id},{default:p(()=>[i(_(T),{class:"w-4 h-4"}),Ut]),_:1},8,["class","disabled"])])):n("",!0),t.value.product_mapping_id?(l(),a("div",Et,[e("div",Nt,[e("div",Rt,[e("div",Ft,[e("table",It,[Tt,e("tbody",Qt,[(l(!0),a(O,null,q(d.deliveryProductMapping.data.deliveryProductMappingVends,(s,w)=>(l(),a("tr",{key:s.id,class:f(w%2===0?void 0:"bg-gray-50")},[e("td",Lt,u(w+1),1),e("td",zt,[m(u(s.vend.code)+" ",1),Kt,m(" ("+u(s.platform_ref_id)+") ",1)]),e("td",Zt,[s.vend.latestVendBinding&&s.vend.latestVendBinding.customer?(l(),a("span",Gt,[m(u(s.vend.latestVendBinding.customer.code)+" ",1),Ht,m(" "+u(s.vend.latestVendBinding.customer.name),1)])):(l(),a("span",Jt,u(s.vend.name),1))]),e("td",Wt,[s.deliveryProductMappingVendChannels?(l(),a("ul",{key:0,class:"sm:grid sm:grid-cols-[105px_minmax(110px,_1fr)_100px] hover:cursor-pointer",onClick:v=>X(s)},[(l(!0),a(O,null,q(s.deliveryProductMappingVendChannels,(v,M)=>(l(),a("li",{class:f(["quick-look",[M>0&&String(v.vend_channel_code)[0]!==String(s.deliveryProductMappingVendChannels[M-1].vend_channel_code)[0]?"col-start-1":""]])},[e("span",{class:f([[M>0&&String(v.vend_channel_code)[0]!==String(s.deliveryProductMappingVendChannels[M-1].vend_channel_code)[0]?"border-t-4 pt-1":""],"flex space-x-2"])},[e("span",null," #"+u(v.vend_channel_code),1),v.is_active==1?(l(),x(_(I),{key:0,class:"w-4 h-4 fill-green-500"})):(l(),x(_(Q),{key:1,class:"w-4 h-4 fill-red-500"}))],2)],2))),256))],8,Xt)):n("",!0)]),e("td",Yt,[s.is_active==1?(l(),a("span",es," Operating ")):n("",!0),s.is_active==0?(l(),a("span",ts," Paused ")):n("",!0)]),e("td",ss,[e("div",os,[i(y,{class:f(["flex space-x-1",[s.is_active?"bg-yellow-300 hover:bg-yellow-400 text-black":"bg-green-500 hover:bg-green-600 text-white"]]),onClick:g(v=>oe(s),["prevent"])},{default:p(()=>[s.is_active?(l(),x(_(Q),{key:0,class:"w-3 h-3"})):(l(),x(_(_e),{key:1,class:"w-3 h-3"})),s.is_active?(l(),a("span",ls," Pause VM ")):(l(),a("span",as," Resume VM "))]),_:2},1032,["class","onClick"]),s.is_active?n("",!0):(l(),x(y,{key:0,class:"flex space-x-1 bg-red-500 hover:bg-red-600 text-white",onClick:g(v=>le(s.id),["prevent"])},{default:p(()=>[i(_(ve),{class:"w-3 h-3"}),rs]),_:2},1032,["onClick"]))])])],2))),128)),d.deliveryProductMapping.data.deliveryProductMappingVends.length?n("",!0):(l(),a("tr",ns,is))])])])])])])):n("",!0)])],32)])])])]),V.value?(l(),x(ce,{key:0,vend:R.value,deliveryProductMapping:d.deliveryProductMapping.data,showModal:V.value,onModalClose:Y},null,8,["vend","deliveryProductMapping","showModal"])):n("",!0),C.value?(l(),x(ue,{key:1,deliveryProductMapping:d.deliveryProductMapping.data,deliveryProductMappingItemObj:N.value,showModal:C.value,onModalClose:te},null,8,["deliveryProductMapping","deliveryProductMappingItemObj","showModal"])):n("",!0)]),_:1})],64))}};export{$s as default};
