import{_ as G}from"./Authenticated.1712ee97.js";import{_ as B}from"./Button.46eac1e9.js";import{d as j}from"./main.d6699238.js";import{o as f,g as v,d as e,r as E,a,b as y,a2 as k,i as g,j as N,w as l,F as M,H as A,t as i,m as F,p as I,c as K,f as r,J as w}from"./app.5ce7e61c.js";import{_ as O,r as H,a as J,T as m,b as R,c as Y,d as c}from"./TableHeadSort.7105f4bb.js";import{_}from"./MultiSelect.b43913d6.js";import"./open-closed.9f96de4f.js";import"./use-resolve-button-type.5ff87d97.js";import"./RectangleStackIcon.09b8722e.js";import"./_plugin-vue_export-helper.cdc0426e.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.5966726e.js";const q={for:"text",class:"block text-sm font-medium text-gray-700"},z={class:"mt-1"},$={__name:"DatePicker",props:{modelValue:[Date,String],minDate:[Date,String],maxDate:[Date,String],enableTimePicker:{type:Boolean,default:!1}},emits:["update:modelValue"],setup(n,{emit:p}){function x(h){p("update:modelValue",k(h).format("Y-M-D"))}return(h,o)=>(f(),v("div",null,[e("label",q,[E(h.$slots,"default")]),e("div",z,[a(y(j),{modelValue:n.modelValue,"onUpdate:modelValue":x,format:"yyyy-MM-dd",clearable:!1,monthChangeOnScroll:!1,autoApply:"",closeOnAutoApply:!0,minDate:n.minDate,maxDate:n.maxDate,enableTimePicker:n.enableTimePicker},null,8,["modelValue","minDate","maxDate","enableTimePicker"])])]))}},Q=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines (Transactions) ",-1),W={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},X={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Z={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},ee={class:"col-span-5 md:col-span-1"},te=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Vend ID ",-1),ae={class:"col-span-5 md:col-span-1"},oe=r(" From "),le={class:"col-span-5 md:col-span-1"},se=r(" To "),ne={class:"col-span-5 md:col-span-1"},de=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Errors? ",-1),re={class:"col-span-5 md:col-span-1"},ie=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Pay Method ",-1),ue={class:"col-span-5 md:col-span-1"},ce=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),me={class:"col-span-5 md:col-span-1"},pe=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),ge=r(" Cust ID "),he=r(" Cust Name "),_e={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},fe={class:"mt-3"},ve={class:"flex space-x-1"},xe=e("span",null," Search ",-1),ye=e("span",null," Reset ",-1),be={class:"flex flex-col space-y-2"},Ve={class:"text-sm text-gray-700 leading-5 flex space-x-1"},Ce=e("span",null,"Showing",-1),ke={class:"font-medium"},Pe=e("span",null,"to",-1),De={class:"font-medium"},Se=e("span",null,"of",-1),Le={class:"font-medium"},Be=e("span",null,"results",-1),Me={class:"mt-6 flex flex-col"},Ie={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},we={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Oe={class:"min-w-full border-separate",style:{"border-spacing":"0"}},$e={class:"bg-gray-100"},Ue={class:"divide-x divide-gray-200"},Te=r(" # "),Ge=r(" Order ID "),je=r(" Transaction DateTime "),Ee=r(" Vend ID "),Ne=r(" Customer "),Ae=r(" Channel "),Fe=r(" Amount "),Ke=r(" Payment Method "),He=r(" Channels Error "),Je=r(" Dispensing Status "),Re={class:"bg-white"},Ye=e("br",null,null,-1),qe={key:0},ze=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Qe=[ze],rt={__name:"Transaction",props:{categories:Object,categoryGroups:Object,paymentMethods:Object,vends:Object,vendTransactions:Object,vendChannelErrors:Object},setup(n){const p=n,x=g([]),h=g([]);N(()=>{P.value=p.vends.data.map(d=>({id:d.id,code:d.code})),D.value=p.vendChannelErrors.data.map(d=>({id:d.id,desc:d.desc})),b.value=[{id:"",name:"All"},...p.paymentMethods.data.map(d=>({id:d.id,name:d.name}))],V.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],o.value.numberPerPage=V.value[0],o.value.paymentMethod=b.value[0],x.value=p.categories.data.map(d=>({id:d.id,name:d.name})),h.value=p.categoryGroups.data.map(d=>({id:d.id,name:d.name}))});const o=g({codes:[],categories:[],categoryGroups:[],customer_code:"",customer_name:"",errors:[],paymentMethod:"",date_from:k().startOf("month").toDate(),date_to:k().toDate(),sortKey:"",sortBy:!1,numberPerPage:100}),P=g([]),D=g([]),b=g([]),V=g([]);function C(){w.Inertia.get("/vends/transactions",{...o.value,categories:o.value.categories.map(d=>d.id),categoryGroups:o.value.categoryGroups.map(d=>d.id),codes:o.value.codes.map(d=>d.id),errors:o.value.errors.map(d=>d.id),paymentMethod:o.value.paymentMethod.id,numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0})}function U(){w.Inertia.get("/vends/transactions")}function T(d){o.value.sortKey=d,o.value.sortBy=!o.value.sortBy,C()}return(d,s)=>(f(),v(M,null,[a(y(A),{title:"VM Transactions"}),a(G,null,{header:l(()=>[Q]),default:l(()=>{var S,L;return[e("div",W,[e("div",X,[e("div",Z,[e("div",ee,[te,a(_,{modelValue:o.value.codes,"onUpdate:modelValue":s[0]||(s[0]=t=>o.value.codes=t),options:P.value,valueProp:"id",label:"code",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),e("div",ae,[a($,{modelValue:o.value.date_from,"onUpdate:modelValue":s[1]||(s[1]=t=>o.value.date_from=t),class:"col-span-5 md:col-span-1"},{default:l(()=>[oe]),_:1},8,["modelValue"])]),e("div",le,[a($,{modelValue:o.value.date_to,"onUpdate:modelValue":s[2]||(s[2]=t=>o.value.date_to=t),minDate:o.value.date_from,class:"col-span-5 md:col-span-1"},{default:l(()=>[se]),_:1},8,["modelValue","minDate"])]),e("div",ne,[de,a(_,{modelValue:o.value.errors,"onUpdate:modelValue":s[3]||(s[3]=t=>o.value.errors=t),options:D.value,valueProp:"id",label:"desc",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),e("div",re,[ie,a(_,{modelValue:o.value.paymentMethod,"onUpdate:modelValue":s[4]||(s[4]=t=>o.value.paymentMethod=t),options:b.value,valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),e("div",ue,[ce,a(_,{modelValue:o.value.categories,"onUpdate:modelValue":s[5]||(s[5]=t=>o.value.categories=t),options:x.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),e("div",me,[pe,a(_,{modelValue:o.value.categoryGroups,"onUpdate:modelValue":s[6]||(s[6]=t=>o.value.categoryGroups=t),options:h.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),a(O,{placeholderStr:"Cust ID",modelValue:o.value.customer_code,"onUpdate:modelValue":s[7]||(s[7]=t=>o.value.customer_code=t)},{default:l(()=>[ge]),_:1},8,["modelValue"]),a(O,{placeholderStr:"Cust Name",modelValue:o.value.customer_name,"onUpdate:modelValue":s[8]||(s[8]=t=>o.value.customer_name=t)},{default:l(()=>[he]),_:1},8,["modelValue"])]),e("div",_e,[e("div",fe,[e("div",ve,[a(B,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[9]||(s[9]=t=>C())},{default:l(()=>[a(y(H),{class:"h-4 w-4","aria-hidden":"true"}),xe]),_:1}),a(B,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[10]||(s[10]=t=>U())},{default:l(()=>[a(y(J),{class:"h-4 w-4","aria-hidden":"true"}),ye]),_:1})])]),e("div",be,[e("p",Ve,[Ce,e("span",ke,i((S=n.vendTransactions.meta.from)!=null?S:0),1),Pe,e("span",De,i((L=n.vendTransactions.meta.to)!=null?L:0),1),Se,e("span",Le,i(n.vendTransactions.meta.total),1),Be]),a(_,{modelValue:o.value.numberPerPage,"onUpdate:modelValue":s[11]||(s[11]=t=>o.value.numberPerPage=t),options:V.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:C},null,8,["modelValue","options"])])])]),e("div",Me,[e("div",Ie,[e("div",we,[e("table",Oe,[e("thead",$e,[e("tr",Ue,[a(m,null,{default:l(()=>[Te]),_:1}),a(m,null,{default:l(()=>[Ge]),_:1}),a(R,{modelName:"transaction_datetime",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[12]||(s[12]=t=>T("transaction_datetime"))},{default:l(()=>[je]),_:1},8,["sortKey","sortBy"]),a(m,null,{default:l(()=>[Ee]),_:1}),a(m,null,{default:l(()=>[Ne]),_:1}),a(m,null,{default:l(()=>[Ae]),_:1}),a(m,null,{default:l(()=>[Fe]),_:1}),a(m,null,{default:l(()=>[Ke]),_:1}),a(m,null,{default:l(()=>[He]),_:1}),a(m,null,{default:l(()=>[Je]),_:1})])]),e("tbody",Re,[(f(!0),v(M,null,F(n.vendTransactions.data,(t,u)=>(f(),v("tr",{key:t.id,class:"divide-x divide-gray-200"},[a(c,{currentIndex:u,totalLength:n.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[r(i(n.vendTransactions.meta.from+u),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:u,totalLength:n.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[r(i(t.order_id),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:u,totalLength:n.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[r(i(t.transaction_datetime),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:u,totalLength:n.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[r(i(t.vend.code),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:u,totalLength:n.vendTransactions.length,inputClass:"text-left"},{default:l(()=>[r(i(t.vend.latestVendBinding&&t.vend.latestVendBinding.customer?t.vend.latestVendBinding.customer.code:null)+" ",1),Ye,r(" "+i(t.vend.latestVendBinding&&t.vend.latestVendBinding.customer?t.vend.latestVendBinding.customer.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:u,totalLength:n.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[r(i(t.vendChannel.code),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:u,totalLength:n.vendTransactions.length,inputClass:"text-right"},{default:l(()=>[r(i(t.amount),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:u,totalLength:n.vendTransactions.length,inputClass:"text-left"},{default:l(()=>[r(i(t.paymentMethod?t.paymentMethod.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:u,totalLength:n.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[r(i(t.vendChannelError?t.vendChannelError.desc:""),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:u,totalLength:n.vendTransactions.length,inputClass:"text-center"},{default:l(()=>[r(i(t.vendTransactionJson.ISOK==1?"Success":"Failure"),1)]),_:2},1032,["currentIndex","totalLength"])]))),128)),n.vendTransactions.data.length?I("",!0):(f(),v("tr",qe,Qe))])]),n.vendTransactions.data.length?(f(),K(Y,{key:0,links:n.vendTransactions.links,meta:n.vendTransactions.meta},null,8,["links","meta"])):I("",!0)])])])])]}),_:1})],64))}};export{rt as default};
