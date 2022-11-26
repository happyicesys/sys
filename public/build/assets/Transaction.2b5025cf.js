import{_ as G}from"./Authenticated.17f9c3cd.js";import{_ as M}from"./Button.27f88839.js";import{d as j}from"./main.94effd7e.js";import{o as f,g as v,d as t,r as A,a,b as y,a2 as k,i as p,j as E,w as l,F as S,H as F,t as i,m as N,p as w,c as K,f as d,J as O}from"./app.c318dc46.js";import{r as H,a as R,T as m,_ as J,b as T,c}from"./TableHeadSort.1bd53b43.js";import{_}from"./MultiSelect.8217c9f4.js";import"./open-closed.4eec9221.js";import"./use-resolve-button-type.88ac71ab.js";import"./RectangleStackIcon.afa5836f.js";import"./_plugin-vue_export-helper.cdc0426e.js";const Y={for:"text",class:"block text-sm font-medium text-gray-700"},q={class:"mt-1"},$={__name:"DatePicker",props:{modelValue:[Date,String],minDate:[Date,String],maxDate:[Date,String],enableTimePicker:{type:Boolean,default:!1}},emits:["update:modelValue"],setup(s,{emit:g}){function x(h){g("update:modelValue",k(h).format("Y-M-D"))}return(h,o)=>(f(),v("div",null,[t("label",Y,[A(h.$slots,"default")]),t("div",q,[a(y(j),{modelValue:s.modelValue,"onUpdate:modelValue":x,format:"yyyy-MM-dd",clearable:!1,monthChangeOnScroll:!1,autoApply:"",closeOnAutoApply:!0,minDate:s.minDate,maxDate:s.maxDate,enableTimePicker:s.enableTimePicker},null,8,["modelValue","minDate","maxDate","enableTimePicker"])])]))}},z=t("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines (Transactions) ",-1),Q={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},W={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},X={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},Z={class:"col-span-5 md:col-span-1"},ee=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Code ",-1),te={class:"col-span-5 md:col-span-1"},ae=d(" From "),oe={class:"col-span-5 md:col-span-1"},le=d(" To "),se={class:"col-span-5 md:col-span-1"},ne=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Errors? ",-1),de={class:"col-span-5 md:col-span-1"},re=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Pay Method ",-1),ie={class:"col-span-5 md:col-span-1"},ue=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),ce={class:"col-span-5 md:col-span-1"},me=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),ge={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},pe={class:"mt-3"},he={class:"flex space-x-1"},_e=t("span",null," Search ",-1),fe=t("span",null," Reset ",-1),ve={class:"flex flex-col space-y-2"},xe={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ye=t("span",null,"Showing",-1),be={class:"font-medium"},Ve=t("span",null,"to",-1),Be={class:"font-medium"},ke=t("span",null,"of",-1),Pe={class:"font-medium"},Ce=t("span",null,"results",-1),Le={class:"mt-6 flex flex-col"},De={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},Me={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Se={class:"min-w-full border-separate",style:{"border-spacing":"0"}},we={class:"bg-gray-100"},Oe={class:"divide-x divide-gray-200"},$e=d(" # "),Ie=d(" DateTime "),Ue=d(" Code "),Ge=d(" Customer "),je=d(" Category "),Ae=d(" Channel "),Ee=d(" Amount "),Fe=d(" Pay Method "),Ne=d(" Channels Error "),Ke=d(" Order ID "),He={class:"bg-white"},Re=t("br",null,null,-1),Je=t("br",null,null,-1),Te={key:0},Ye=t("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),qe=[Ye],st={__name:"Transaction",props:{categories:Object,categoryGroups:Object,paymentMethods:Object,vends:Object,vendTransactions:Object,vendChannelErrors:Object},setup(s){const g=s,x=p([]),h=p([]);E(()=>{P.value=g.vends.data.map(n=>({id:n.id,code:n.code})),C.value=g.vendChannelErrors.data.map(n=>({id:n.id,desc:n.desc})),b.value=[{id:"",name:"All"},...g.paymentMethods.data.map(n=>({id:n.id,name:n.name}))],V.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],o.value.numberPerPage=V.value[0],o.value.paymentMethod=b.value[0],x.value=g.categories.data.map(n=>({id:n.id,name:n.name})),h.value=g.categoryGroups.data.map(n=>({id:n.id,name:n.name}))});const o=p({codes:[],categories:[],categoryGroups:[],errors:[],paymentMethod:"",date_from:k().startOf("month").toDate(),date_to:k().toDate(),sortKey:"",sortBy:!1,numberPerPage:100}),P=p([]),C=p([]),b=p([]),V=p([]);function B(){O.Inertia.get("/vends/transactions",{...o.value,categories:o.value.categories.map(n=>n.id),categoryGroups:o.value.categoryGroups.map(n=>n.id),codes:o.value.codes.map(n=>n.id),errors:o.value.errors.map(n=>n.id),paymentMethod:o.value.paymentMethod.id,numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0})}function I(){O.Inertia.get("/vends/transactions")}function U(n){o.value.sortKey=n,o.value.sortBy=!o.value.sortBy,B()}return(n,r)=>(f(),v(S,null,[a(y(F),{title:"VM Transactions"}),a(G,null,{header:l(()=>[z]),default:l(()=>{var L,D;return[t("div",Q,[t("div",W,[t("div",X,[t("div",Z,[ee,a(_,{modelValue:o.value.codes,"onUpdate:modelValue":r[0]||(r[0]=e=>o.value.codes=e),options:P.value,valueProp:"id",label:"code",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",te,[a($,{modelValue:o.value.date_from,"onUpdate:modelValue":r[1]||(r[1]=e=>o.value.date_from=e),class:"col-span-5 md:col-span-1"},{default:l(()=>[ae]),_:1},8,["modelValue"])]),t("div",oe,[a($,{modelValue:o.value.date_to,"onUpdate:modelValue":r[2]||(r[2]=e=>o.value.date_to=e),minDate:o.value.date_from,class:"col-span-5 md:col-span-1"},{default:l(()=>[le]),_:1},8,["modelValue","minDate"])]),t("div",se,[ne,a(_,{modelValue:o.value.errors,"onUpdate:modelValue":r[3]||(r[3]=e=>o.value.errors=e),options:C.value,valueProp:"id",label:"desc",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",de,[re,a(_,{modelValue:o.value.paymentMethod,"onUpdate:modelValue":r[4]||(r[4]=e=>o.value.paymentMethod=e),options:b.value,valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",ie,[ue,a(_,{modelValue:o.value.categories,"onUpdate:modelValue":r[5]||(r[5]=e=>o.value.categories=e),options:x.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",ce,[me,a(_,{modelValue:o.value.categoryGroups,"onUpdate:modelValue":r[6]||(r[6]=e=>o.value.categoryGroups=e),options:h.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),t("div",ge,[t("div",pe,[t("div",he,[a(M,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:r[7]||(r[7]=e=>B())},{default:l(()=>[a(y(H),{class:"h-4 w-4","aria-hidden":"true"}),_e]),_:1}),a(M,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:r[8]||(r[8]=e=>I())},{default:l(()=>[a(y(R),{class:"h-4 w-4","aria-hidden":"true"}),fe]),_:1})])]),t("div",ve,[t("p",xe,[ye,t("span",be,i((L=s.vendTransactions.meta.from)!=null?L:0),1),Ve,t("span",Be,i((D=s.vendTransactions.meta.to)!=null?D:0),1),ke,t("span",Pe,i(s.vendTransactions.meta.total),1),Ce]),a(_,{modelValue:o.value.numberPerPage,"onUpdate:modelValue":r[9]||(r[9]=e=>o.value.numberPerPage=e),options:V.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:B},null,8,["modelValue","options"])])])]),t("div",Le,[t("div",De,[t("div",Me,[t("table",Se,[t("thead",we,[t("tr",Oe,[a(m,null,{default:l(()=>[$e]),_:1}),a(J,{modelName:"transaction_datetime",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:r[10]||(r[10]=e=>U("transaction_datetime"))},{default:l(()=>[Ie]),_:1},8,["sortKey","sortBy"]),a(m,null,{default:l(()=>[Ue]),_:1}),a(m,null,{default:l(()=>[Ge]),_:1}),a(m,null,{default:l(()=>[je]),_:1}),a(m,null,{default:l(()=>[Ae]),_:1}),a(m,null,{default:l(()=>[Ee]),_:1}),a(m,null,{default:l(()=>[Fe]),_:1}),a(m,null,{default:l(()=>[Ne]),_:1}),a(m,null,{default:l(()=>[Ke]),_:1})])]),t("tbody",He,[(f(!0),v(S,null,N(s.vendTransactions.data,(e,u)=>(f(),v("tr",{key:e.id,class:"divide-x divide-gray-200"},[a(c,{currentIndex:u,totalLength:s.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[d(i(s.vendTransactions.meta.from+u),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:u,totalLength:s.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[d(i(e.transaction_datetime),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:u,totalLength:s.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[d(i(e.vend.code),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:u,totalLength:s.vendTransactions.length,inputClass:"text-left"},{default:l(()=>[d(i(e.vend.latestVendBinding&&e.vend.latestVendBinding.customer?e.vend.latestVendBinding.customer.code:null)+" ",1),Re,d(" "+i(e.vend.latestVendBinding&&e.vend.latestVendBinding.customer?e.vend.latestVendBinding.customer.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:u,totalLength:s.vendTransactions.length,inputClass:"text-left"},{default:l(()=>[d(i(e.vend.latestVendBinding&&e.vend.latestVendBinding.customer&&e.vend.latestVendBinding.customer.category?e.vend.latestVendBinding.customer.category.name:null)+" ",1),Je,d(" "+i(e.vend.latestVendBinding&&e.vend.latestVendBinding.customer&&e.vend.latestVendBinding.customer.category&&e.vend.latestVendBinding.customer.category.category_group?e.vend.latestVendBinding.customer.category.category_group.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:u,totalLength:s.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[d(i(e.vendChannel.code),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:u,totalLength:s.vendTransactions.length,inputClass:"text-right"},{default:l(()=>[d(i(e.amount),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:u,totalLength:s.vendTransactions.length,inputClass:"text-left"},{default:l(()=>[d(i(e.paymentMethod?e.paymentMethod.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:u,totalLength:s.vendTransactions.length,inputClass:"text-left"},{default:l(()=>[d(i(e.vend_channel_error_desc),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:u,totalLength:s.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[d(i(e.order_id),1)]),_:2},1032,["currentIndex","totalLength"])]))),128)),s.vendTransactions.data.length?w("",!0):(f(),v("tr",Te,qe))])]),s.vendTransactions.data.length?(f(),K(T,{key:0,links:s.vendTransactions.links,meta:s.vendTransactions.meta},null,8,["links","meta"])):w("",!0)])])])])]}),_:1})],64))}};export{st as default};
