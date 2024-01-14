import{_ as z}from"./Authenticated.d9d2160d.js";import{_ as $}from"./Button.42c5086d.js";import{_ as U}from"./DatePicker.e42e4191.js";import{_ as H}from"./Paginator.d173555f.js";import{_ as O,r as G}from"./SearchInput.11bf622e.js";import{_ as k}from"./MultiSelect.7e7dfced.js";import{o as i,f as c,b as e,g,U as T,Q as Y,h as W,a as l,u as f,w as o,F as b,Z as X,d as a,c as V,l as h,t as d,k as D,O as E,i as ee,n as te}from"./app.bb8a700c.js";import le from"./Complaint.1d021554.js";import{_ as x,a as _}from"./TableData.a43878b8.js";import{_ as K}from"./TableHeadSort.2a8e74cd.js";import{r as se}from"./BackspaceIcon.7f1fffe2.js";import{r as oe}from"./ArrowDownTrayIcon.cf56bdb9.js";import"./keyboard.3e63a9f3.js";import"./use-resolve-button-type.06468222.js";import"./RectangleStackIcon.ca11d7b1.js";import"./main.46054eb5.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.c933c3a4.js";import"./Modal.d80888fe.js";import"./disposables.c51067c6.js";import"./ArrowUturnLeftIcon.5d8a203e.js";function ne(r,L){return i(),c("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true","data-slot":"icon"},[e("path",{"fill-rule":"evenodd",d:"M10 2c-2.236 0-4.43.18-6.57.524C1.993 2.755 1 4.014 1 5.426v5.148c0 1.413.993 2.67 2.43 2.902.848.137 1.705.248 2.57.331v3.443a.75.75 0 0 0 1.28.53l3.58-3.579a.78.78 0 0 1 .527-.224 41.202 41.202 0 0 0 5.183-.5c1.437-.232 2.43-1.49 2.43-2.903V5.426c0-1.413-.993-2.67-2.43-2.902A41.289 41.289 0 0 0 10 2Zm0 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2ZM8 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm5 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z","clip-rule":"evenodd"})])}const ae=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Delivery Platform Orders ",-1),re={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},de={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},ie={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},ue=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Delivery Platform ",-1),ce=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Status ",-1),me=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Has Complaint? ",-1),_e={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},pe={class:"mt-3"},fe={class:"flex space-x-1"},he=e("span",null," Search ",-1),xe=e("span",null," Reset ",-1),ge={class:"flex space-x-1"},ve={key:1,"aria-hidden":"true",class:"mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600",viewBox:"0 0 100 101",fill:"none",xmlns:"http://www.w3.org/2000/svg"},ye=e("path",{d:"M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z",fill:"currentColor"},null,-1),be=e("path",{d:"M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z",fill:"currentFill"},null,-1),Ce=[ye,be],we=e("span",null," Export Excel ",-1),ke={class:"flex flex-col space-y-2"},Ve={class:"text-sm text-gray-700 leading-5 flex space-x-1"},De=e("span",null,"Showing",-1),Le={class:"font-medium"},Se=e("span",null,"to",-1),Ie={class:"font-medium"},Pe=e("span",null,"of",-1),Me={class:"font-medium"},Be=e("span",null,"results",-1),$e={class:"mt-2 grid grid-cols-1 gap-5 sm:grid-cols-3"},Oe={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},Te=e("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Amount (Delivered)",-1),je={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},Ne={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},Fe=e("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Orders (Delivered)",-1),Ue={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},Ye={class:"mt-6 flex flex-col"},Ee={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8 px-3"},Ke={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Ze={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Ae={class:"bg-gray-100"},qe={class:"divide-x divide-gray-200"},Je=e("br",null,null,-1),Qe={class:"bg-white"},Re={key:0},ze=e("br",null,null,-1),He={class:"w-xs"},Ge={class:"flex flex-col"},We={class:"font-semibold grow-0"},Xe={key:0,class:"text-xs"},et=e("br",null,null,-1),tt=e("br",null,null,-1),lt={class:"divide-y divide-gray-200"},st={class:"flex py-1 px-3 space-x-2"},ot={class:"self-center font-semibold text-blue-700"},nt={key:0},at=e("br",null,null,-1),rt=e("span",{class:"self-center"}," x ",-1),dt={class:"self-center"},it={class:"flex flex-col space-y-1"},ut={class:"inline-flex items-center rounded-md bg-purple-50 px-1 py-1 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-700/10 hover:text-clip"},ct={class:"inline-flex items-center rounded px-2 py-0.5 text-xs"},mt={key:0,class:"inline-flex items-center rounded px-2 py-0.5 text-xs font-medium border bg-red-100 text-red-800"},_t={class:"flex flex-col space-x-1"},pt={class:"font-bold"},ft={class:"flex flex-col space-y-1"},ht=["onClick"],xt={class:"flex space-x-1"},gt=e("span",{class:"font-semibold"}," Complaint ",-1),vt={key:0},yt=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),bt=[yt],Kt={__name:"Index",props:{deliveryPlatformOrders:Object,deliveryPlatformOperatorOptions:Object,deliveryPlatformOrderStatusOptions:Object,totals:Object},setup(r){const L=r,S=g([]),s=g({order_id:"",short_order_id:"",vend_code:"",date_from:T().format("YYYY-MM-DD"),date_to:T().format("YYYY-MM-DD"),delivery_platform_operator_id:"",has_complaint:"all",sortKey:"",sortBy:!1,status:"",numberPerPage:100}),I=g([]);g();const C=g(!1),j=g(),y=Y().props.auth.operatorCountry,P=g([]),Z=Y().props.auth.permissions,w=g(!1);g(!1),W(()=>{S.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],P.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],s.value.numberPerPage=P.value[0],I.value=[{id:"all",name:"All"},...L.deliveryPlatformOperatorOptions.data.map(u=>({id:u.id,name:u.deliveryPlatform.name+" ("+u.type+")"}))],s.value.delivery_platform_operator_id=I.value[0],s.value.has_complaint=S.value[0],s.value.status=L.deliveryPlatformOrderStatusOptions[0]});function A(u,n){return u.deliveryProductMappingVend.deliveryPlatformCampaignItemVends.find(v=>v.platform_ref_id==n).deliveryPlatformCampaignItem.settings_name}function q(u){j.value=u,w.value=!0}function J(){w.value=!1}function Q(){C.value=!0,axios({method:"get",url:"/delivery-platform-orders/excel",params:{...s.value,delivery_platform_operator_id:s.value.delivery_platform_operator_id.id,has_complaint:s.value.has_complaint.id,status:s.value.status.id},responseType:"blob"}).then(u=>{fileDownload(u.data,"Delivery_Platform_Order_"+T().format("YYMMDDhhmmss")+".xlsx")}).catch(u=>{console.log(u)}).finally(()=>{C.value=!1})}function M(){E.get("/delivery-platform-orders",{...s.value,delivery_platform_operator_id:s.value.delivery_platform_operator_id.id,status:s.value.status.id,has_complaint:s.value.has_complaint.id,numberPerPage:s.value.numberPerPage.id},{preserveState:!0,replace:!0})}function R(){E.get("/delivery-platform-orders")}function N(u){s.value.sortKey=u,s.value.sortBy=!s.value.sortBy,M()}function B(u){let n="",v="";switch(u.status){case 1:case 2:n="bg-blue-400 text-gray-800";break;case 3:case 4:case 5:case 6:case 7:n="bg-yellow-400 text-gray-800";break;case 8:n="bg-green-400 text-white-800";break;case 98:case 99:n="bg-red-400 text-white-800",v=u.request_history_json.code+" ("+u.request_history_json.message+")";break}return{statusClass:n,statusDesc:v}}return(u,n)=>(i(),c(b,null,[l(f(X),{title:"Delivery Platform"}),l(z,null,{header:o(()=>[ae]),default:o(()=>{var v,F;return[e("div",re,[e("div",de,[e("div",ie,[l(O,{placeholderStr:"Name",modelValue:s.value.order_id,"onUpdate:modelValue":n[0]||(n[0]=t=>s.value.order_id=t)},{default:o(()=>[a(" Order ID ")]),_:1},8,["modelValue"]),l(O,{placeholderStr:"Name",modelValue:s.value.short_order_id,"onUpdate:modelValue":n[1]||(n[1]=t=>s.value.short_order_id=t)},{default:o(()=>[a(" Short Order ID ")]),_:1},8,["modelValue"]),l(O,{placeholderStr:"Vend ID",modelValue:s.value.vend_code,"onUpdate:modelValue":n[2]||(n[2]=t=>s.value.vend_code=t)},{default:o(()=>[a(" Vend ID ")]),_:1},8,["modelValue"]),l(U,{modelValue:s.value.date_from,"onUpdate:modelValue":n[3]||(n[3]=t=>s.value.date_from=t)},{default:o(()=>[a(" From ")]),_:1},8,["modelValue"]),l(U,{modelValue:s.value.date_to,"onUpdate:modelValue":n[4]||(n[4]=t=>s.value.date_to=t),minDate:s.value.date_from},{default:o(()=>[a(" To ")]),_:1},8,["modelValue","minDate"]),e("div",null,[ue,l(k,{modelValue:s.value.delivery_platform_operator_id,"onUpdate:modelValue":n[5]||(n[5]=t=>s.value.delivery_platform_operator_id=t),options:I.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),e("div",null,[ce,l(k,{modelValue:s.value.status,"onUpdate:modelValue":n[6]||(n[6]=t=>s.value.status=t),options:r.deliveryPlatformOrderStatusOptions,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),e("div",null,[me,l(k,{modelValue:s.value.has_complaint,"onUpdate:modelValue":n[7]||(n[7]=t=>s.value.has_complaint=t),options:S.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),e("div",_e,[e("div",pe,[e("div",fe,[l($,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[8]||(n[8]=t=>M())},{default:o(()=>[l(f(G),{class:"h-4 w-4","aria-hidden":"true"}),he]),_:1}),l($,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[9]||(n[9]=t=>R())},{default:o(()=>[l(f(se),{class:"h-4 w-4","aria-hidden":"true"}),xe]),_:1}),f(Z).includes("export excel")?(i(),V($,{key:0,type:"button",class:"rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100",onClick:n[10]||(n[10]=t=>Q())},{default:o(()=>[e("div",ge,[e("div",null,[C.value?h("",!0):(i(),V(f(oe),{key:0,class:"h-4 w-4","aria-hidden":"true"})),C.value?(i(),c("svg",ve,Ce)):h("",!0)]),we])]),_:1})):h("",!0)])]),e("div",ke,[e("p",Ve,[De,e("span",Le,d((v=r.deliveryPlatformOrders.meta.from)!=null?v:0),1),Se,e("span",Ie,d((F=r.deliveryPlatformOrders.meta.to)!=null?F:0),1),Pe,e("span",Me,d(r.deliveryPlatformOrders.meta.total),1),Be]),l(k,{modelValue:s.value.numberPerPage,"onUpdate:modelValue":n[11]||(n[11]=t=>s.value.numberPerPage=t),options:P.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:M},null,8,["modelValue","options"])])]),e("dl",$e,[e("div",Oe,[Te,e("dd",je,d((r.totals.total_amount/Math.pow(10,f(y).currency_exponent)).toLocaleString(void 0,{minimumFractionDigits:f(y).is_currency_exponent_hidden?0:f(y).currency_exponent})),1)]),e("div",Ne,[Fe,e("dd",Ue,d(r.totals.order_count.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)])])]),e("div",Ye,[e("div",Ee,[e("div",Ke,[e("table",Ze,[e("thead",Ae,[e("tr",qe,[l(x,null,{default:o(()=>[a(" # ")]),_:1}),l(K,{modelName:"order_created_at",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:n[12]||(n[12]=t=>N("order_created_at"))},{default:o(()=>[a(" Order Time ")]),_:1},8,["sortKey","sortBy"]),l(x,null,{default:o(()=>[a(" Platform ")]),_:1}),l(x,null,{default:o(()=>[a(" Order ID ")]),_:1}),l(x,null,{default:o(()=>[a(" Short Order ID ")]),_:1}),l(x,null,{default:o(()=>[a(" Status ")]),_:1}),l(K,{modelName:"vend_code",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:n[13]||(n[13]=t=>N("vend_code"))},{default:o(()=>[a(" Vend ")]),_:1},8,["sortKey","sortBy"]),l(x,null,{default:o(()=>[a(" Transaction "),Je,a(" Order ID ")]),_:1}),l(x,null,{default:o(()=>[a(" (Channel) Item x Qty ")]),_:1}),l(x,null,{default:o(()=>[a(" Subtotal ")]),_:1}),l(x,null,{default:o(()=>[a(" Campaign ")]),_:1}),l(x,null,{default:o(()=>[a(" Channel Error(s) ")]),_:1}),l(x,null,{default:o(()=>[a(" Driver Phone Number ")]),_:1})])]),e("tbody",Qe,[(i(!0),c(b,null,D(r.deliveryPlatformOrders.data,(t,m)=>(i(),c("tr",{key:t.id,class:"divide-x divide-gray-200"},[l(_,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:o(()=>[a(d(r.deliveryPlatformOrders.meta.from+m),1)]),_:2},1032,["currentIndex","totalLength"]),l(_,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:o(()=>[a(d(t.order_created_at),1)]),_:2},1032,["currentIndex","totalLength"]),l(_,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:o(()=>[a(d(t&&t.deliveryPlatform?t.deliveryPlatform.name:null)+" ",1),t.deliveryPlatformOperator?(i(),c("span",Re,[ze,a("("+d(t.deliveryPlatformOperator?t.deliveryPlatformOperator.type:null)+") ",1)])):h("",!0)]),_:2},1032,["currentIndex","totalLength"]),l(_,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:o(()=>[l(f(ee),{href:"/delivery-platform-orders/"+t.id+"/edit",class:"text-blue-600"},{default:o(()=>[a(d(t.order_id),1)]),_:2},1032,["href"])]),_:2},1032,["currentIndex","totalLength"]),l(_,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:o(()=>[a(d(t.short_order_id),1)]),_:2},1032,["currentIndex","totalLength"]),l(_,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center w-xs"},{default:o(()=>[e("div",He,[e("div",{class:te(["inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-medium border w-xs",B(t).statusClass])},[e("div",Ge,[e("span",We,d(t.status_name),1)])],2),B(t).statusDesc?(i(),c("span",Xe,[et,a(" "+d(B(t).statusDesc),1)])):h("",!0)])]),_:2},1032,["currentIndex","totalLength"]),l(_,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-left"},{default:o(()=>[a(d(t.vend_code)+" ",1),tt,a(" "+d(t.deliveryProductMappingVend.vend.cust_full_name),1)]),_:2},1032,["currentIndex","totalLength"]),l(_,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:o(()=>[a(d(t.vend_transaction_order_id),1)]),_:2},1032,["currentIndex","totalLength"]),l(_,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-left"},{default:o(()=>[e("ul",lt,[(i(!0),c(b,null,D(t.deliveryPlatformOrderItems,p=>(i(),c("li",st,[e("span",ot,[p.orderItemVendChannels[0]?(i(),c("span",nt," (#"+d(p.orderItemVendChannels[0].vend_channel_code)+") ",1)):h("",!0)]),e("span",null,[a(d(p.deliveryProductMappingItem.product.code)+" ",1),at,a(" "+d(p.deliveryProductMappingItem.product.name),1)]),rt,e("span",dt,d(p.qty),1)]))),256))])]),_:2},1032,["currentIndex","totalLength"]),l(_,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-right"},{default:o(()=>[a(d(t.subtotal_amount.toLocaleString(void 0,{minimumFractionDigits:f(y).is_currency_exponent_hidden?0:f(y).currency_exponent})),1)]),_:2},1032,["currentIndex","totalLength"]),l(_,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:o(()=>[e("div",it,[(i(!0),c(b,null,D(t.virtual_campaign_id_json,p=>(i(),c("span",ut,d(A(t,p)),1))),256))])]),_:2},1032,["currentIndex","totalLength"]),l(_,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:o(()=>[t.vendTransaction&&t.vendTransaction.itemsJson?(i(!0),c(b,{key:0},D(t.vendTransaction.itemsJson,p=>(i(),c("span",ct,[p.vendChannelError!=null?(i(),c("span",mt,[e("div",_t,[e("div",null,[a(" #"+d(p.vendChannelCode)+" ",1),e("span",pt,d(p.vendChannelError.desc),1)])])])):h("",!0)]))),256)):h("",!0)]),_:2},1032,["currentIndex","totalLength"]),l(_,{currentIndex:m,totalLength:r.deliveryPlatformOrders.length,inputClass:"text-center"},{default:o(()=>[e("div",ft,[e("span",null,d(t.driver_phone_number),1),t.deliveryPlatformOrderComplaint?(i(),c("div",{key:0,class:"inline-flex justify-center items-center rounded px-1.5 py-1 text-xs font-medium border min-w-full bg-yellow-400 text-gray-800 hover:cursor-pointer",onClick:p=>q(t)},[e("div",xt,[l(f(ne),{class:"h-4 w-4","aria-hidden":"true"}),gt])],8,ht)):h("",!0)])]),_:2},1032,["currentIndex","totalLength"])]))),128)),r.deliveryPlatformOrders.data.length?h("",!0):(i(),c("tr",vt,bt))])]),r.deliveryPlatformOrders.data.length?(i(),V(H,{key:0,links:r.deliveryPlatformOrders.links,meta:r.deliveryPlatformOrders.meta},null,8,["links","meta"])):h("",!0)])])])]),w.value?(i(),V(le,{key:0,model:j.value,showModal:w.value,onModalClose:J},null,8,["model","showModal"])):h("",!0)]}),_:1})],64))}};export{Kt as default};
