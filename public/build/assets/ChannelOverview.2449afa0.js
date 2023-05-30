import{_ as h}from"./Modal.0f52e20a.js";import{j as g,K as f,c as v,a as w,w as _,s as b,o,b as e,f as n,m as r,t as d,d as p,u as l,l as k,F as C,n as x}from"./app.47c1a29c.js";import"./open-closed.1532f125.js";const B={class:"flex flex-col md:flex-row space-x-2"},D={key:0,class:"text-gray-600"},M={key:1},N={key:2},V={class:"flex flex-col"},j={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},F={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},O={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},S={class:"table-fixed min-w-full divide-y divide-gray-300"},$={class:"bg-gray-50"},q=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," # ",-1),E={key:0,scope:"col",class:"w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},I=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Sold ",-1),L=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Bal ",-1),P=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Cap ",-1),T={scope:"col",class:"w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},Y={key:0},z=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Error ",-1),A={key:1,scope:"col",class:"w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},J={class:"bg-white"},K={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},G={key:0,class:"whitespace-nowrap text-sm font-semibold text-gray-900 text-center"},H={class:"flex justify-center items-center"},Q=["src"],R={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-blue-600 sm:pl-6 text-center"},U={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},W={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},X={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Z={class:"py-1 pl-1 pr-1 text-xs font-medium text-gray-900 sm:pl-1 text-center"},ee={key:0},te={class:"font-bold"},se={key:1,class:"py-4 text-sm font-semibold text-gray-900 text-center"},oe={key:0},ne={key:1,class:"break-normal text-xs"},re=e("br",null,null,-1),de={key:0,class:"flex flex-col items-end text-blue-800 text-sm p-3"},ce={key:0,class:""},ae={key:1},pe={__name:"ChannelOverview",props:{vend:Object,showModal:Boolean},emits:["modalClose"],setup(s,{emit:le}){const i=s;g(()=>{console.log(i.vend)});const c=f().props.auth.profile;function u(a){return moment(a).format("YYMMDD hh:mm A")}return(a,m)=>(o(),v(b,{to:"body"},[w(h,{open:s.showModal,onModalClose:m[0]||(m[0]=t=>a.$emit("modalClose"))},{header:_(()=>[e("div",B,[i.profile?(o(),n("span",D," Channel Overview ")):r("",!0),s.vend.code?(o(),n("span",M," ID# "+d(s.vend.code),1)):r("",!0),s.vend.customer_code?(o(),n("span",N," ("+d(s.vend.customer_code?s.vend.customer_code:null)+") "+d(s.vend.customer_name?s.vend.customer_name:null),1)):r("",!0)])]),default:_(()=>[e("div",V,[e("div",j,[e("div",F,[e("div",O,[e("table",S,[e("thead",$,[e("tr",null,[q,s.vend.product_mapping_name?(o(),n("th",E," Image ")):r("",!0),I,L,P,e("th",T,[p(" Price "),l(c)&&l(c).base_currency?(o(),n("span",Y," ("+d(l(c).base_currency.currency_symbol)+") ",1)):r("",!0)]),z,s.vend.product_mapping_name?(o(),n("th",A," Product ")):r("",!0)])]),e("tbody",J,[(o(!0),n(C,null,k(s.vend.vendChannelsJson,(t,y)=>(o(),n("tr",{key:t.id,class:x(y%2===0?void 0:"bg-gray-50")},[e("td",K,d(t.code),1),s.vend.product_mapping_name?(o(),n("td",G,[e("div",H,[t.product&&t.product.thumbnail?(o(),n("img",{key:0,class:"h-16 w-16 rounded-full",src:t.product.thumbnail.full_url,alt:""},null,8,Q)):r("",!0)])])):r("",!0),e("td",R,d(t.capacity-t.qty),1),e("td",U,d(t.qty),1),e("td",W,d(t.capacity),1),e("td",X,d((t.amount/100).toLocaleString(void 0,{minimumFractionDigits:2})),1),e("td",Z,[t.vend_channel_error_logs&&t.vend_channel_error_logs[0]&&t.vend_channel_error_logs[0].is_error_cleared==0?(o(),n("span",ee,[e("div",{class:x([t.vend_channel_error_logs[0].vend_channel_error.code==4||t.vend_channel_error_logs[0].vend_channel_error.code==5||t.vend_channel_error_logs[0].vend_channel_error.code==7?" text-blue-800":" text-red-800"])},[e("span",te," ("+d(t.vend_channel_error_logs[0].vend_channel_error.code)+") ",1),e("div",null,d(u(t.vend_channel_error_logs[0].created_at)),1)],2)])):r("",!0)]),s.vend.product_mapping_name?(o(),n("td",se,[t.product&&t.product.code?(o(),n("span",oe,d(t.product.code),1)):r("",!0),t.product&&t.product.name?(o(),n("span",ne,[re,p(" "+d(t.product.name),1)])):r("",!0)])):r("",!0)],2))),128))])])])])])]),s.vend.product_mapping_name?(o(),n("p",de,[s.vend.product_mapping_name?(o(),n("span",ce,d(s.vend.product_mapping_name),1)):r("",!0),s.vend.product_mapping_remarks?(o(),n("span",ae,d(s.vend.product_mapping_remarks),1)):r("",!0)])):r("",!0)]),_:1},8,["open"])]))}};export{pe as default};