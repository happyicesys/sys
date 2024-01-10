import{g as c,T as B,Q as re,h as ne,j as D,f as a,a as i,u as m,w as p,F as O,o as l,Z as de,b as e,d as _,t as u,l as n,e as g,i as ie,n as f,k as q,c as x,O as k}from"./app.8f9fd870.js";import{_ as ce}from"./Authenticated.9c7a946e.js";import{_ as y}from"./Button.d49610f5.js";import ue from"./ChannelOverview.2dc3d3d0.js";import pe from"./EditItem.f958ad9a.js";import{_ as h}from"./FormInput.49c7bc80.js";import{_ as A}from"./MultiSelect.dc61c537.js";import{r as me}from"./ArrowUturnLeftIcon.cc000bde.js";import{r as I}from"./CheckCircleIcon.b923e539.js";import{r as T}from"./PlusCircleIcon.171635a6.js";import{r as _e}from"./PencilSquareIcon.65220a2d.js";import{r as Q}from"./PauseCircleIcon.6a1cff51.js";import{r as ve}from"./PlayCircleIcon.f8d9c909.js";import{r as fe}from"./XCircleIcon.c8bca08a.js";import"./keyboard.339bd933.js";import"./use-resolve-button-type.fcc9a671.js";import"./RectangleStackIcon.5f55e086.js";import"./Modal.11ef5155.js";import"./disposables.9598f4cc.js";import"./BackspaceIcon.38303ee3.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.7c3f3866.js";const xe={class:"font-semibold text-xl text-gray-800 leading-tight"},ye={key:0},ge={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},he={class:"mt-6 flex flex-col"},be={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},we={class:"shadow-sm ring-1 ring-black ring-opacity-5 p-5"},ke={class:"grid grid-cols-1 gap-3 sm:grid-cols-6 pb-2"},Pe={class:"sm:col-span-6"},Se={class:"sm:col-span-6"},Ve=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Operator ",-1),Ce={class:"mt-1"},Me=["value"],Oe={class:"sm:col-span-6"},je=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Delivery Platform ",-1),$e={class:"mt-1"},Be=["value"],De={class:"sm:col-span-6"},qe=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Platform Category ",-1),Ae={class:"mt-1"},Ue=["value"],Ee={class:"sm:col-span-6"},Ne=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Refer to Product Mapping ",-1),Re={class:"mt-1"},Fe=["value"],Ie={class:"sm:col-span-3"},Te={class:"sm:col-span-3"},Qe=e("div",{class:"sm:col-span-6"},[e("label",{for:"reserved",class:"italic text-blue-800"},' By setting "Reserved Percentage" and "Reserved Quantity", the sellable qty equivalent to whichever higher. If lower than reserved, channel becomes inactive, both default value are 0. ')],-1),Le={class:"sm:col-span-6"},ze={class:"flex space-x-1 mt-5 justify-end"},Ke=e("span",null," Back ",-1),Ze=e("span",null," Save ",-1),Ge=e("div",{class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},[e("div",{class:"relative"},[e("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[e("div",{class:"w-full border-t border-gray-300"})]),e("div",{class:"relative flex justify-center"},[e("span",{class:"px-3 bg-white text-lg font-medium text-gray-900 rounded"},"Delivery Platform Product(s) ")])])],-1),He={key:0},Je={key:1},We=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Product ",-1),Xe={key:0,class:"text-sm text-red-600"},Ye={key:2},et={key:3},tt=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Platform SubCategory ",-1),st={key:4,class:"sm:col-span-1"},ot=e("span",null," Add ",-1),lt={key:5,class:"sm:col-span-6 flex flex-col mt-3"},at={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},rt={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},nt={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},dt={class:"table-fixed min-w-full divide-y divide-gray-300"},it=e("thead",{class:"bg-gray-50"},[e("tr",null,[e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," # "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Channel ID "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Thumbnail "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Product "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Status "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Price "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Platform SubCategory "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Action ")])],-1),ct={class:"bg-white"},ut={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},pt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},mt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},_t={class:"flex justify-center"},vt=["src"],ft={class:"whitespace-normal py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left flex flex-col"},xt={key:0},yt={key:1,class:"break-words"},gt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},ht={key:0,class:"inline-flex items-center rounded-md bg-green-300 px-1.5 py-0.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-indigo-700/10"},bt={key:1,class:"inline-flex items-center rounded-md bg-red-200 px-1.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-indigo-700/10"},wt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},kt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},Pt={class:"whitespace-nowrap py-4 pr-2 text-sm text-center"},St=e("span",{class:"text-xs"}," Edit ",-1),Vt={key:0},Ct=e("td",{colspan:"7",class:"whitespace-nowrap py-4 text-sm font-medium text-gray-600 text-center"}," No Records Found ",-1),Mt=[Ct],Ot={key:6,class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},jt=e("div",{class:"relative"},[e("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[e("div",{class:"w-full border-t border-gray-300"})]),e("div",{class:"relative flex justify-center"},[e("span",{class:"px-3 bg-white text-lg font-medium text-gray-900 rounded"}," Vending Machine Binding ")])],-1),$t=[jt],Bt={key:7,class:"sm:col-span-3"},Dt=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Vending Machine ",-1),qt={key:0,class:"text-sm text-red-600"},At={key:8,class:"sm:col-span-2"},Ut={key:9,class:"sm:col-span-1"},Et=e("span",null," Add ",-1),Nt={key:10,class:"sm:col-span-6 flex flex-col mt-3"},Rt={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},Ft={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},It={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},Tt={class:"min-w-full divide-y divide-gray-300"},Qt=e("thead",{class:"bg-gray-50"},[e("tr",null,[e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," # "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"},[_(" Vend ID "),e("br"),_(" (Platform Ref ID) ")]),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Vend Name "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Channel Status "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," VM Status "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Action ")])],-1),Lt={class:"bg-white"},zt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Kt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},Zt=e("br",null,null,-1),Gt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left"},Ht={key:0},Jt=e("br",null,null,-1),Wt={key:1},Xt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},Yt=["onClick"],es={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},ts={key:0,class:"inline-flex items-center rounded-md bg-green-300 px-1.5 py-0.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-indigo-700/10"},ss={key:1,class:"inline-flex items-center rounded-md bg-red-200 px-1.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-indigo-700/10"},os={class:"whitespace-nowrap py-2 text-xs text-center p-3"},ls={class:"flex flex-col space-y-1"},as={key:2,class:"text-xs"},rs={key:3,class:"text-xs"},ns=e("span",{class:"text-xs"},"Unbind VM",-1),ds={key:0},is=e("td",{colspan:"6",class:"whitespace-nowrap py-4 text-sm font-medium text-gray-600 text-center"}," No Records Found ",-1),cs=[is],Bs={__name:"Edit",props:{bundleSalesOptions:[Array,Object],deliveryProductMapping:Object,productMappingItems:Object,productOptions:Object,type:String,unbindedVendOptions:[Array,Object]},setup(U){const d=U,E=c(""),b=c([]),L=c([]),P=c([]),z=c([]),N=c([]),K=c([]),t=c(B(F()));c(!1);const S=c(!0),Z=c([]),G=c([]),V=c(!1),C=c(!1),H=c([]),j=c(""),J=re().props.auth.roles,R=c(),$=c([]);ne(()=>{d.type=="create"?j.value="Create New":j.value="Edit",L.value=[...d.bundleSalesOptions.map(o=>({id:o.id,name:o.name,is_same:o.is_same,type:o.type,phrase_1:o.phrase_1,phrase_2:o.phrase_2,phrase_3:o.phrase_3}))],P.value=d.deliveryProductMapping.data,z.value=d.deliveryProductMapping.data.deliveryProductMappingBulks,K.value=d.deliveryProductMapping.data.deliveryProductMappingItems.map(o=>({id:o.id,full_name:"(#"+o.channel_code+") "+o.product.code+" "+o.product.name,img_url:o.product.thumbnail.full_url,amount:o.amount})),t.value=P.value?B(P.value):B(F()),$.value=[...d.unbindedVendOptions.data.map(o=>({id:o.id,full_name:o.full_name}))]}),D(function(){return t.value.promo_label&&t.value.promo_value&&t.value.total_qty?t.value.promo_label.phrase_1+t.value.total_qty+t.value.promo_label.phrase_2+t.value.promo_value+t.value.promo_label.phrase_3:""}),D(function(){let o=!0;return E.value="",(!t.value.promo_label||!t.value.promo_value||!t.value.delivery_product_mapping_item_id)&&(o=!1),t.value.total_qty==0&&(o=!1),S.value&&b.value.length==1&&(o=!1),!S.value&&b.value.length>0&&b.value.filter(r=>t.value.delivery_product_mapping_item_id.id==r.delivery_product_mapping_item_id.id).length>0&&(o=!1,E.value="Product already added"),o}),D(function(){let o=!1;return b.value.length>1&&!S.value&&(o=!0),b.value.length==1&&S.value&&(o=!0),o});function F(){return{id:"",bundle_name:"",category_json:"",delivery_platform_operator_id:"",name:"",operator_id:"",platform_ref_id:"",product_mapping_id:"",promo_label:"",promo_type:"",promo_value:"",reserved_percent:0,reserved_qty:0,sub_category_json:"",total_amount:"",total_qty:""}}function W(){Z.value.map(function(o){return o.channel_code}).indexOf(t.value.channel_code)<0&&k.post("/delivery-product-mapping-items/delivery-product-mapping/"+t.value.id+"/store",{...t.value,product_id:t.value.product_id?t.value.product_id.id:null},{preserveState:!1,preserveScroll:!0,replace:!0})}function X(o){k.post("/delivery-product-mappings/"+t.value.id+"/bind-vend",{vend_id:o,platform_ref_id:t.value.platform_ref_id},{preserveState:!1,preserveScroll:!0,replace:!0})}function Y(o){R.value=o,V.value=!0}function ee(){V.value=!1}function te(o){N.value=o,C.value=!0}function se(){C.value=!1}function oe(){t.value.clearErrors(),t.value.transform(o=>({name:o.name,reserved_percent:o.reserved_percent,reserved_qty:o.reserved_qty})).post("/delivery-product-mappings/"+t.value.id+"/update",{preserveState:!0,replace:!0})}function le(o){let r=o.is_active?"Are you sure to pause this vending machine?":"Are you sure to resume this vending machine?";!confirm(r)||k.post("/delivery-product-mappings/vends/"+o.id+"/toggle-pause-vend",{},{preserveState:!1,preserveScroll:!0,replace:!0})}function ae(o){!confirm("Are you sure to delete this entry?")||k.delete("/delivery-product-mappings/unbind/"+o,{preserveState:!1,preserveScroll:!0,replace:!0,onSuccess:()=>{k.reload({only:["unbindedVendOptions"]}),$.value=d.unbindedVendOptions?d.unbindedVendOptions.data:[]}})}return(o,r)=>(l(),a(O,null,[i(m(de),{title:"Delivery Product Mapping"}),i(ce,null,{header:p(()=>[e("h2",xe,[_(u(j.value)+" Delivery Product Mapping ",1),U.type=="update"?(l(),a("span",ye,u(P.value.name),1)):n("",!0)])]),default:p(()=>[e("div",ge,[e("div",he,[e("div",be,[e("div",we,[e("form",{onSubmit:g(oe,["prevent"]),id:"submit"},[e("div",ke,[e("div",Pe,[i(h,{modelValue:t.value.name,"onUpdate:modelValue":r[0]||(r[0]=s=>t.value.name=s),error:t.value.errors.name},{default:p(()=>[_(" Name ")]),_:1},8,["modelValue","error"])]),e("div",Se,[Ve,e("div",Ce,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:d.deliveryProductMapping.data.operator.full_name,disabled:""},null,8,Me)])]),e("div",Oe,[je,e("div",$e,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:d.deliveryProductMapping.data.deliveryPlatformOperator.deliveryPlatform.name+" ("+d.deliveryProductMapping.data.deliveryPlatformOperator.type+")",disabled:""},null,8,Be)])]),e("div",De,[qe,e("div",Ae,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:d.deliveryProductMapping.data.category_json.id+" - "+d.deliveryProductMapping.data.category_json.name,disabled:""},null,8,Ue)])]),e("div",Ee,[Ne,e("div",Re,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:d.deliveryProductMapping.data.productMapping.name,disabled:""},null,8,Fe)])]),e("div",Ie,[i(h,{modelValue:t.value.reserved_percent,"onUpdate:modelValue":r[1]||(r[1]=s=>t.value.reserved_percent=s),error:t.value.errors.reserved_percent},{default:p(()=>[_(" Reserved Percentage (%) ")]),_:1},8,["modelValue","error"])]),e("div",Te,[i(h,{modelValue:t.value.reserved_qty,"onUpdate:modelValue":r[2]||(r[2]=s=>t.value.reserved_qty=s),error:t.value.errors.reserved_qty},{default:p(()=>[_(" Reserved Quantity ")]),_:1},8,["modelValue","error"])]),Qe,e("div",Le,[e("div",ze,[i(m(ie),{href:"/delivery-product-mappings"},{default:p(()=>[i(y,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"},{default:p(()=>[i(m(me),{class:"w-4 h-4"}),Ke]),_:1})]),_:1}),i(y,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:p(()=>[i(m(I),{class:"w-4 h-4"}),Ze]),_:1})])]),Ge,t.value.id?(l(),a("div",He,[i(h,{modelValue:t.value.channel_code,"onUpdate:modelValue":r[3]||(r[3]=s=>t.value.channel_code=s),error:t.value.errors.channel_code,placeholderStr:"Channel ID"},{default:p(()=>[_(" Channel ID ")]),_:1},8,["modelValue","error"])])):n("",!0),t.value.id?(l(),a("div",Je,[We,i(A,{modelValue:t.value.product_id,"onUpdate:modelValue":r[4]||(r[4]=s=>t.value.product_id=s),options:G.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),t.value.errors.product_id?(l(),a("div",Xe,u(t.value.errors.product_id),1)):n("",!0)])):n("",!0),t.value.id?(l(),a("div",Ye,[i(h,{modelValue:t.value.amount,"onUpdate:modelValue":r[5]||(r[5]=s=>t.value.amount=s),error:t.value.errors.amount,placeholderStr:"Platform Price"},{default:p(()=>[_(" Price ")]),_:1},8,["modelValue","error"])])):n("",!0),t.value.id?(l(),a("div",et,[tt,i(A,{modelValue:t.value.sub_category_json,"onUpdate:modelValue":r[6]||(r[6]=s=>t.value.sub_category_json=s),options:H.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):n("",!0),t.value.product_mapping_id?(l(),a("div",st,[i(y,{type:"button",onClick:r[7]||(r[7]=g(s=>W(),["prevent"])),class:f(["bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6",[!t.value.channel_code||!t.value.product_id?"opacity-50 cursor-not-allowed":""]]),disabled:!t.value.channel_code||!t.value.product_id},{default:p(()=>[i(m(T),{class:"w-4 h-4"}),ot]),_:1},8,["class","disabled"])])):n("",!0),t.value.product_mapping_id?(l(),a("div",lt,[e("div",at,[e("div",rt,[e("div",nt,[e("table",dt,[it,e("tbody",ct,[(l(!0),a(O,null,q(d.deliveryProductMapping.data.deliveryProductMappingItems,(s,w)=>(l(),a("tr",{key:s.id,class:f(w%2===0?void 0:"bg-gray-50")},[e("td",ut,u(w+1),1),e("td",pt,u(s.channel_code),1),e("td",mt,[e("div",_t,[s.product&&s.product.thumbnail?(l(),a("img",{key:0,class:"h-24 w-24 md:h-20 md:w-20 rounded-full",src:s.product.thumbnail.full_url,alt:""},null,8,vt)):n("",!0)])]),e("td",ft,[s.product.code?(l(),a("span",xt,u(s.product.code),1)):n("",!0),s.product.name?(l(),a("span",yt,u(s.product.name),1)):n("",!0)]),e("td",gt,[s.is_active==1?(l(),a("span",ht," Active ")):n("",!0),s.is_active==0?(l(),a("span",bt," Paused ")):n("",!0)]),e("td",wt,u(s.amount.toLocaleString(void 0,{minimumFractionDigits:d.deliveryProductMapping.data.operator.country.is_currency_exponent_hidden?0:d.deliveryProductMapping.data.operator.country.currency_exponent,maximumFractionDigits:d.deliveryProductMapping.data.operator.country.is_currency_exponent_hidden?0:d.deliveryProductMapping.data.operator.country.currency_exponent})),1),e("td",kt,u(s.sub_category_json.name),1),e("td",Pt,[i(y,{class:"bg-gray-300 hover:bg-gray-400 text-black flex space-x-1",onClick:g(v=>te(s),["prevent"])},{default:p(()=>[i(m(_e),{class:"w-3 h-3"}),St]),_:2},1032,["onClick"])])],2))),128)),!d.deliveryProductMapping.data.deliveryProductMappingItems||!d.deliveryProductMapping.data.deliveryProductMappingItems.length?(l(),a("tr",Vt,Mt)):n("",!0)])])])])])])):n("",!0),t.value.product_mapping_id?(l(),a("div",Ot,$t)):n("",!0),t.value.product_mapping_id?(l(),a("div",Bt,[Dt,i(A,{modelValue:t.value.vend_id,"onUpdate:modelValue":r[8]||(r[8]=s=>t.value.vend_id=s),options:$.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),t.value.errors.vend_id?(l(),a("div",qt,u(t.value.errors.vend_id),1)):n("",!0)])):n("",!0),t.value.product_mapping_id?(l(),a("div",At,[i(h,{modelValue:t.value.platform_ref_id,"onUpdate:modelValue":r[9]||(r[9]=s=>t.value.platform_ref_id=s),error:t.value.errors.platform_ref_id,placeholderStr:"Platform ID"},{default:p(()=>[_(" Platform ID (Store ID) ")]),_:1},8,["modelValue","error"])])):n("",!0),t.value.product_mapping_id?(l(),a("div",Ut,[i(y,{type:"button",onClick:r[10]||(r[10]=g(s=>X(t.value.vend_id.id),["prevent"])),class:f(["bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6",[t.value.vend_id?"":"opacity-50 cursor-not-allowed"]]),disabled:!t.value.vend_id},{default:p(()=>[i(m(T),{class:"w-4 h-4"}),Et]),_:1},8,["class","disabled"])])):n("",!0),t.value.product_mapping_id?(l(),a("div",Nt,[e("div",Rt,[e("div",Ft,[e("div",It,[e("table",Tt,[Qt,e("tbody",Lt,[(l(!0),a(O,null,q(d.deliveryProductMapping.data.deliveryProductMappingVends,(s,w)=>(l(),a("tr",{key:s.id,class:f(w%2===0?void 0:"bg-gray-50")},[e("td",zt,u(w+1),1),e("td",Kt,[_(u(s.vend.code)+" ",1),Zt,_(" ("+u(s.platform_ref_id)+") ",1)]),e("td",Gt,[s.vend.latestVendBinding&&s.vend.latestVendBinding.customer?(l(),a("span",Ht,[_(u(s.vend.latestVendBinding.customer.code)+" ",1),Jt,_(" "+u(s.vend.latestVendBinding.customer.name),1)])):(l(),a("span",Wt,u(s.vend.name),1))]),e("td",Xt,[s.deliveryProductMappingVendChannels?(l(),a("ul",{key:0,class:"sm:grid sm:grid-cols-[105px_minmax(110px,_1fr)_100px] hover:cursor-pointer",onClick:v=>Y(s)},[(l(!0),a(O,null,q(s.deliveryProductMappingVendChannels,(v,M)=>(l(),a("li",{class:f(["quick-look",[M>0&&String(v.vend_channel_code)[0]!==String(s.deliveryProductMappingVendChannels[M-1].vend_channel_code)[0]?"col-start-1":""]])},[e("span",{class:f([[M>0&&String(v.vend_channel_code)[0]!==String(s.deliveryProductMappingVendChannels[M-1].vend_channel_code)[0]?"border-t-4 pt-1":""],"flex space-x-2"])},[e("span",null," #"+u(v.vend_channel_code),1),v.is_active==1?(l(),x(m(I),{key:0,class:"w-4 h-4 fill-green-500"})):(l(),x(m(Q),{key:1,class:"w-4 h-4 fill-red-500"}))],2)],2))),256))],8,Yt)):n("",!0)]),e("td",es,[s.is_active==1?(l(),a("span",ts," Operating ")):n("",!0),s.is_active==0?(l(),a("span",ss," Paused ")):n("",!0)]),e("td",os,[e("div",ls,[i(y,{class:f(["flex space-x-1",[s.is_active?"bg-yellow-300 hover:bg-yellow-400 text-black":"bg-green-500 hover:bg-green-600 text-white"]]),onClick:g(v=>le(s),["prevent"])},{default:p(()=>[s.is_active?(l(),x(m(Q),{key:0,class:"w-3 h-3"})):(l(),x(m(ve),{key:1,class:"w-3 h-3"})),s.is_active?(l(),a("span",as," Pause VM ")):(l(),a("span",rs," Resume VM "))]),_:2},1032,["class","onClick"]),m(J).includes("superadmin")&&!s.is_active?(l(),x(y,{key:0,class:"flex space-x-1 bg-red-500 hover:bg-red-600 text-white",onClick:g(v=>ae(s.id),["prevent"])},{default:p(()=>[i(m(fe),{class:"w-3 h-3"}),ns]),_:2},1032,["onClick"])):n("",!0)])])],2))),128)),d.deliveryProductMapping.data.deliveryProductMappingVends.length?n("",!0):(l(),a("tr",ds,cs))])])])])])])):n("",!0)])],32)])])])]),V.value?(l(),x(ue,{key:0,vend:R.value,deliveryProductMapping:d.deliveryProductMapping.data,showModal:V.value,onModalClose:ee},null,8,["vend","deliveryProductMapping","showModal"])):n("",!0),C.value?(l(),x(pe,{key:1,deliveryProductMapping:d.deliveryProductMapping.data,deliveryProductMappingItemObj:N.value,showModal:C.value,onModalClose:se},null,8,["deliveryProductMapping","deliveryProductMappingItemObj","showModal"])):n("",!0)]),_:1})],64))}};export{Bs as default};