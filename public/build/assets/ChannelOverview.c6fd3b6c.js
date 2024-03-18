import{g as f,Q as V,c as p,a as u,w as y,p as S,o,b as e,f as r,t as d,l,d as a,k as $,F as B,n as v,e as h,u as x,O as b}from"./app.b43730ab.js";import{_ as w}from"./Button.58bd898c.js";import{_ as P}from"./FormInput.f3c4cd1e.js";import{_ as q}from"./Modal.9c75dfdd.js";import{r as D}from"./CheckCircleIcon.c521a4b4.js";import{r as O}from"./PencilSquareIcon.3a23cef5.js";import{r as N,a as Q}from"./PlayCircleIcon.24dfe4e6.js";import"./keyboard.47431282.js";import"./disposables.3d165ee4.js";const U={class:"flex flex-col md:flex-row space-x-2"},j=e("span",{class:"text-gray-600"}," Delivery Product Mapping Channel ",-1),A={key:0},E={key:1},F={class:"flex flex-col"},I={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},R={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},T={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},L={class:"table-auto min-w-full divide-y divide-gray-300"},z={class:"bg-gray-50"},G=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," # ",-1),H=e("th",{scope:"col",class:"w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Image ",-1),J=e("th",{scope:"col",class:"w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Product ",-1),K={scope:"col",class:"w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},W={key:0},X=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},[a(" Reserved "),e("br"),a(" (%) ")],-1),Y=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},[a(" Reserved "),e("br"),a(" Qty ")],-1),Z=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},[a(" Booked "),e("br"),a(" Qty ")],-1),ee=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},[a(" Current "),e("br"),a(" Qty ")],-1),te=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},[a(" Current "),e("br"),a(" Capacity ")],-1),se=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Status ",-1),oe=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},null,-1),re={class:"bg-white"},ie={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},de={class:"whitespace-nowrap text-sm font-semibold text-gray-900 text-center"},ae={class:"flex justify-center items-center"},ce=["src"],le={class:"py-4 text-sm font-semibold text-gray-900 text-center"},ne={key:0},pe={key:1,class:"break-normal text-xs"},me=e("br",null,null,-1),ue={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},ye={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-red-700 sm:pl-6 text-center"},xe={key:0},_e={key:1},ge={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-red-700 sm:pl-6 text-center"},ve={key:0},fe={key:1},he={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-blue-700 sm:pl-6 text-center"},be={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-blue-700 sm:pl-6 text-center"},we={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-blue-700 sm:pl-6 text-center"},Pe={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Me={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},ke={key:0,class:"inline-flex items-center rounded-md bg-green-300 px-1.5 py-0.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-indigo-700/10"},Ce={key:1,class:"inline-flex items-center rounded-md bg-red-200 px-1.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-indigo-700/10"},Ve={class:"whitespace-nowrap py-2 pl-4 pr-2 text-sm font-normal text-gray-900 sm:pl-3 text-center flex flex-col space-y-1 py-3"},Se={key:2,class:"text-xs"},$e={key:3,class:"text-xs"},Be={key:2,class:"text-xs"},qe={key:3,class:"text-xs"},Ie={__name:"ChannelOverview",props:{deliveryProductMapping:Object,vend:Object,showModal:Boolean},emits:["modalClose"],setup(s,{emit:M}){const _=s;f([]),V().props.auth.permissions,f(!1);const g=M;function k(c){let n=c.is_active?"Are you sure to pause this channel?":"Are you sure to resume this channel?";!confirm(n)||(b.post("/delivery-product-mappings/channels/"+c.id+"/toggle-pause",{preserveState:!1,preserveScroll:!0,replace:!0,onSuccess:()=>{g("modalClose")}}),g("modalClose"))}function C(c){_.vend.deliveryProductMappingVendChannels[_.vend.deliveryProductMappingVendChannels.findIndex(n=>n.id===c.id)].is_editable=!c.is_editable,c.is_editable||b.post("/delivery-product-mappings/channels/"+c.id+"/update",c,{preserveState:!1,preserveScroll:!0,replace:!0,onSuccess:()=>{g("modalClose")}})}return(c,n)=>(o(),p(S,{to:"body"},[u(q,{open:s.showModal,onModalClose:n[0]||(n[0]=t=>c.$emit("modalClose"))},{header:y(()=>[e("div",U,[j,s.vend.code?(o(),r("span",A," ID# "+d(s.vend.code),1)):l("",!0),s.vend.customer_code?(o(),r("span",E," ("+d(s.vend.customer_code?s.vend.customer_code:null)+") "+d(s.vend.customer_name?s.vend.customer_name:null),1)):l("",!0)])]),default:y(()=>[e("div",F,[e("div",I,[e("div",R,[e("div",T,[e("table",L,[e("thead",z,[e("tr",null,[G,H,J,e("th",K,[a(" Price "),s.deliveryProductMapping.operator&&s.deliveryProductMapping.operator.country?(o(),r("span",W," ("+d(s.deliveryProductMapping.operator.country.currency_symbol)+") ",1)):l("",!0)]),X,Y,Z,ee,te,se,oe])]),e("tbody",re,[(o(!0),r(B,null,$(_.vend.deliveryProductMappingVendChannels,(t,i)=>(o(),r("tr",{key:t.id,class:v(i%2===0?void 0:"bg-gray-50")},[e("td",ie,d(t.vend_channel_code),1),e("td",de,[e("div",ae,[s.deliveryProductMapping.deliveryProductMappingItems[i]&&s.deliveryProductMapping.deliveryProductMappingItems[i].product&&s.deliveryProductMapping.deliveryProductMappingItems[i].product.thumbnail&&s.deliveryProductMapping.deliveryProductMappingItems[i].channel_code==t.vend_channel_code?(o(),r("img",{key:0,class:"h-16 w-16 rounded-full",src:s.deliveryProductMapping.deliveryProductMappingItems[i].product.thumbnail.full_url,alt:""},null,8,ce)):l("",!0)])]),e("td",le,[s.deliveryProductMapping.deliveryProductMappingItems[i]&&s.deliveryProductMapping.deliveryProductMappingItems[i].product&&s.deliveryProductMapping.deliveryProductMappingItems[i].channel_code==t.vend_channel_code?(o(),r("span",ne,d(s.deliveryProductMapping.deliveryProductMappingItems[i].product.code),1)):l("",!0),s.deliveryProductMapping.deliveryProductMappingItems[i]&&s.deliveryProductMapping.deliveryProductMappingItems[i].product&&s.deliveryProductMapping.deliveryProductMappingItems[i].channel_code==t.vend_channel_code?(o(),r("span",pe,[me,a(" "+d(s.deliveryProductMapping.deliveryProductMappingItems[i].product.name),1)])):l("",!0)]),e("td",ue,d(t.amount.toLocaleString(void 0,{minimumFractionDigits:2})),1),e("td",ye,[t.is_editable?(o(),r("span",_e,[u(P,{modelValue:t.reserved_percent,"onUpdate:modelValue":m=>t.reserved_percent=m},null,8,["modelValue","onUpdate:modelValue"])])):(o(),r("span",xe,d(t.reserved_percent),1))]),e("td",ge,[t.is_editable?(o(),r("span",fe,[u(P,{modelValue:t.reserved_qty,"onUpdate:modelValue":m=>t.reserved_qty=m},null,8,["modelValue","onUpdate:modelValue"])])):(o(),r("span",ve,d(t.reserved_qty),1))]),e("td",he,d(t.order_qty),1),e("td",be,d(t.vend_channel.qty),1),e("td",we,d(t.vend_channel.capacity),1),e("td",Pe,[e("td",Me,[t.is_active==1?(o(),r("span",ke," Active ")):l("",!0),t.is_active==0?(o(),r("span",Ce," Paused ")):l("",!0)])]),e("td",Ve,[u(w,{class:v(["flex space-x-1",[t.is_editable?"bg-green-500 hover:bg-green-600 text-white":"bg-gray-400 hover:bg-gray-800 text-black"]]),onClick:h(m=>C(t),["prevent"])},{default:y(()=>[t.is_editable?(o(),p(x(D),{key:0,class:"w-3 h-3"})):(o(),p(x(O),{key:1,class:"w-3 h-3"})),t.is_editable?(o(),r("span",Se," Save ")):(o(),r("span",$e," Edit "))]),_:2},1032,["class","onClick"]),t.is_editable?l("",!0):(o(),p(w,{key:0,class:v(["flex space-x-1",[t.is_active?"bg-yellow-300 hover:bg-yellow-400 text-black":"bg-green-500 hover:bg-green-600 text-white"]]),onClick:h(m=>k(t),["prevent"])},{default:y(()=>[t.is_active?(o(),p(x(N),{key:0,class:"w-3 h-3"})):(o(),p(x(Q),{key:1,class:"w-3 h-3"})),t.is_active?(o(),r("span",Be," Pause ")):(o(),r("span",qe," Resume "))]),_:2},1032,["class","onClick"]))])],2))),128))])])])])])])]),_:1},8,["open"])]))}};export{Ie as default};
