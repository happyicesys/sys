import{h as v,K as I,j as Z,f as h,a,u as _,w as l,F as U,o as u,Z as Y,b as o,P as T,d as r,c as K,m as p,t as d,l as H,O as j}from"./app.2e8abb67.js";import{_ as q}from"./Authenticated.41ddfe2d.js";import{_ as D}from"./Button.71ddc537.js";import{_ as M,r as z,T as w,a as J,b as c}from"./TableData.e8cb1b1b.js";import{_ as x}from"./MultiSelect.5235b62b.js";import{_ as f}from"./TableHeadSort.e7224df2.js";import{r as Q}from"./BackspaceIcon.f0d68cc2.js";import{r as W}from"./ArrowDownTrayIcon.d414f642.js";import"./open-closed.fb241ae1.js";import"./use-resolve-button-type.06951a88.js";import"./RectangleStackIcon.d5a51270.js";import"./_plugin-vue_export-helper.cdc0426e.js";const X=o("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Gross Profit by Category ",-1),ee={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},te={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},oe={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},se=o("span",{class:"text-[9px]"},' ("," for multiple) ',-1),ae={key:2},le=o("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Customer Binded? ",-1),ne={key:3},re=o("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),ie={key:4},de=o("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),ue={key:5},me=o("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),ce=o("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Location Type ",-1),ge=o("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Current Month ",-1),_e={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},pe={class:"mt-3"},fe={class:"flex space-x-1"},ve=o("span",null," Search ",-1),he=o("span",null," Reset ",-1),ye={key:1,"aria-hidden":"true",class:"mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600",viewBox:"0 0 100 101",fill:"none",xmlns:"http://www.w3.org/2000/svg"},xe=o("path",{d:"M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z",fill:"currentColor"},null,-1),be=o("path",{d:"M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z",fill:"currentFill"},null,-1),Ce=[xe,be],Be=o("span",null," Export Excel ",-1),we={class:"flex flex-col space-y-2"},Se={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ke=o("span",null,"Showing",-1),Ke={class:"font-medium"},Ve=o("span",null,"to",-1),Le={class:"font-medium"},$e=o("span",null,"of",-1),Pe={class:"font-medium"},Te=o("span",null,"results",-1),De={class:"mt-2 grid grid-cols-1 gap-5 sm:grid-cols-3"},Me={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},Fe=o("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Sales before GST (This Month)",-1),Ge={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},Oe={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},Ne=o("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Gross Profit (This Month)",-1),Ie={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},Ue={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},je=o("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Gross Margin (This Month)",-1),Ae={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},Ee={class:"mt-6 flex flex-col"},Re={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},Ze={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll m-2"},Ye={class:"min-w-full border-separate",style:{"border-spacing":"0"}},He={class:"bg-gray-100"},qe={class:"divide-x divide-gray-200"},ze={class:"divide-x divide-gray-200"},Je={class:"bg-white"},Qe={key:0},We=o("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Xe=[We],ct={__name:"IndexCategory",props:{categories:Object,categoryGroups:Object,locationTypeOptions:Object,monthOptions:Object,operators:Object,totals:[Array,Object],categories:Object},setup(i){const B=i,e=v({categories:[],categoryGroups:[],codes:"",currentMonth:"",customer_code:"",customer_name:"",is_binded_customer:"",location_type_id:"",operator_id:"",sortKey:"",sortBy:!1,numberPerPage:100,visited:!1}),S=v([]),F=v([]),G=v([]),k=v(!1),V=v([]),L=v([]),$=v([]),A=I().props.auth.operatorRole,P=v([]),b=I().props.auth.permissions;Z(()=>{e.value.visited=!0,P.value=[{id:50,value:50},{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],e.value.numberPerPage=P.value[0],S.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],F.value=B.categories.data.map(n=>({id:n.id,name:n.name})),G.value=B.categoryGroups.data.map(n=>({id:n.id,name:n.name})),V.value=[{id:"all",value:"All"},...B.locationTypeOptions.data.map(n=>({id:n.id,value:n.name}))],L.value=B.monthOptions.map(n=>({id:n.id,name:n.name})),e.value.is_binded_customer=A.value?S.value[0]:S.value[1],e.value.location_type_id=V.value[0],e.value.currentMonth=L.value[0],$.value=[{id:"all",full_name:"All"},...B.operators.data.map(n=>({id:n.id,full_name:n.full_name}))],e.value.operator_id=$.value[0]});function C(){j.get("/reports/category",{...e.value,categories:e.value.categories.map(n=>n.id),categoryGroups:e.value.categoryGroups.map(n=>n.id),currentMonth:e.value.currentMonth.id,is_binded_customer:e.value.is_binded_customer.id,location_type_id:e.value.location_type_id.id,operator_id:e.value.operator_id.id,numberPerPage:e.value.numberPerPage.id},{preserveState:!0,replace:!0})}function E(){j.get("/reports/category")}function g(n){e.value.sortKey=n,e.value.sortBy=!e.value.sortBy,C()}function R(){k.value=!0,axios({method:"get",url:"/reports/category/excel",params:{...e.value,categories:e.value.categories.map(n=>n.id),categoryGroups:e.value.categoryGroups.map(n=>n.id),currentMonth:e.value.currentMonth.id,is_binded_customer:e.value.is_binded_customer.id,location_type_id:e.value.location_type_id.id,operator_id:e.value.operator_id.id},responseType:"blob"}).then(n=>{fileDownload(n.data,"UnitCostByCategory_"+moment().format("YYMMDDhhmmss")+".xlsx")}).catch(n=>{console.log(n)}).finally(()=>{k.value=!1})}return(n,t)=>(u(),h(U,null,[a(_(Y),{title:"GP by Category"}),a(q,null,{header:l(()=>[X]),default:l(()=>{var O,N;return[o("div",ee,[o("div",te,[o("div",oe,[a(M,{placeholderStr:"Vend ID",modelValue:e.value.codes,"onUpdate:modelValue":t[0]||(t[0]=s=>e.value.codes=s),onKeyup:t[1]||(t[1]=T(s=>C(),["enter"]))},{default:l(()=>[r(" Vend ID "),se]),_:1},8,["modelValue"]),_(b).includes("admin-access vends")?(u(),K(M,{key:0,placeholderStr:"Cust ID",modelValue:e.value.customer_code,"onUpdate:modelValue":t[2]||(t[2]=s=>e.value.customer_code=s),onKeyup:t[3]||(t[3]=T(s=>C(),["enter"]))},{default:l(()=>[r(" Cust ID ")]),_:1},8,["modelValue"])):p("",!0),_(b).includes("admin-access vends")?(u(),K(M,{key:1,placeholderStr:"Cust Name",modelValue:e.value.customer_name,"onUpdate:modelValue":t[4]||(t[4]=s=>e.value.customer_name=s),onKeyup:t[5]||(t[5]=T(s=>C(),["enter"]))},{default:l(()=>[r(" Cust Name ")]),_:1},8,["modelValue"])):p("",!0),_(b).includes("admin-access vends")?(u(),h("div",ae,[le,a(x,{modelValue:e.value.is_binded_customer,"onUpdate:modelValue":t[6]||(t[6]=s=>e.value.is_binded_customer=s),options:S.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):p("",!0),_(b).includes("admin-access vends")?(u(),h("div",ne,[re,a(x,{modelValue:e.value.operator_id,"onUpdate:modelValue":t[7]||(t[7]=s=>e.value.operator_id=s),options:$.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):p("",!0),_(b).includes("admin-access vends")?(u(),h("div",ie,[de,a(x,{modelValue:e.value.categories,"onUpdate:modelValue":t[8]||(t[8]=s=>e.value.categories=s),options:F.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):p("",!0),_(b).includes("admin-access vends")?(u(),h("div",ue,[me,a(x,{modelValue:e.value.categoryGroups,"onUpdate:modelValue":t[9]||(t[9]=s=>e.value.categoryGroups=s),options:G.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):p("",!0),o("div",null,[ce,a(x,{modelValue:e.value.location_type_id,"onUpdate:modelValue":t[10]||(t[10]=s=>e.value.location_type_id=s),options:V.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),o("div",null,[ge,a(x,{modelValue:e.value.currentMonth,"onUpdate:modelValue":t[11]||(t[11]=s=>e.value.currentMonth=s),options:L.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),o("div",_e,[o("div",pe,[o("div",fe,[a(D,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:t[12]||(t[12]=s=>C())},{default:l(()=>[a(_(z),{class:"h-4 w-4","aria-hidden":"true"}),ve]),_:1}),a(D,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:t[13]||(t[13]=s=>E())},{default:l(()=>[a(_(Q),{class:"h-4 w-4","aria-hidden":"true"}),he]),_:1}),a(D,{class:"inline-flex space-x-1 items-center rounded-md border border-gray-600 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:t[14]||(t[14]=s=>R())},{default:l(()=>[k.value?p("",!0):(u(),K(_(W),{key:0,class:"h-4 w-4","aria-hidden":"true"})),k.value?(u(),h("svg",ye,Ce)):p("",!0),Be]),_:1})])]),o("div",we,[o("p",Se,[ke,o("span",Ke,d((O=i.categories.meta.from)!=null?O:0),1),Ve,o("span",Le,d((N=i.categories.meta.to)!=null?N:0),1),$e,o("span",Pe,d(i.categories.meta.total),1),Te]),a(x,{modelValue:e.value.numberPerPage,"onUpdate:modelValue":t[15]||(t[15]=s=>e.value.numberPerPage=s),options:P.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:C},null,8,["modelValue","options"])])]),o("dl",De,[o("div",Me,[Fe,o("dd",Ge,d(i.totals.revenue.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),o("div",Oe,[Ne,o("dd",Ie,d(i.totals.gross_profit.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),o("div",Ue,[je,o("dd",Ae,d(i.totals.gross_profit_margin.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0}))+" % ",1)])])]),o("div",Ee,[o("div",Re,[o("div",Ze,[o("table",Ye,[o("thead",He,[o("tr",qe,[a(w,null,{default:l(()=>[r(" # ")]),_:1}),a(f,{modelName:"name",sortKey:e.value.sortKey,sortBy:e.value.sortBy,onSortTable:t[16]||(t[16]=s=>g("name"))},{default:l(()=>[r(" Name ")]),_:1},8,["sortKey","sortBy"]),a(w,{colspan:"3"},{default:l(()=>[r(" This Month ")]),_:1}),a(w,{colspan:"3"},{default:l(()=>[r(" Last Month ")]),_:1}),a(w,{colspan:"3"},{default:l(()=>[r(" Last 2 Month ")]),_:1})]),o("tr",ze,[a(w,{colspan:"2"}),a(f,{modelName:"this_month_revenue",sortKey:e.value.sortKey,sortBy:e.value.sortBy,onSortTable:t[17]||(t[17]=s=>g("this_month_revenue"))},{default:l(()=>[r(" Sales ($) ")]),_:1},8,["sortKey","sortBy"]),a(f,{modelName:"this_month_gross_profit",sortKey:e.value.sortKey,sortBy:e.value.sortBy,onSortTable:t[18]||(t[18]=s=>g("this_month_gross_profit"))},{default:l(()=>[r(" GP ($) ")]),_:1},8,["sortKey","sortBy"]),a(f,{modelName:"this_month_gross_profit_margin",sortKey:e.value.sortKey,sortBy:e.value.sortBy,onSortTable:t[19]||(t[19]=s=>g("this_month_gross_profit_margin"))},{default:l(()=>[r(" GM (%) ")]),_:1},8,["sortKey","sortBy"]),a(f,{modelName:"last_month_revenue",sortKey:e.value.sortKey,sortBy:e.value.sortBy,onSortTable:t[20]||(t[20]=s=>g("last_month_revenue"))},{default:l(()=>[r(" Sales ($) ")]),_:1},8,["sortKey","sortBy"]),a(f,{modelName:"last_month_gross_profit",sortKey:e.value.sortKey,sortBy:e.value.sortBy,onSortTable:t[21]||(t[21]=s=>g("last_month_gross_profit"))},{default:l(()=>[r(" GP ($) ")]),_:1},8,["sortKey","sortBy"]),a(f,{modelName:"last_month_gross_profit_margin",sortKey:e.value.sortKey,sortBy:e.value.sortBy,onSortTable:t[22]||(t[22]=s=>g("last_month_gross_profit_margin"))},{default:l(()=>[r(" GM (%) ")]),_:1},8,["sortKey","sortBy"]),a(f,{modelName:"last_two_month_revenue",sortKey:e.value.sortKey,sortBy:e.value.sortBy,onSortTable:t[23]||(t[23]=s=>g("last_two_month_revenue"))},{default:l(()=>[r(" Sales ($) ")]),_:1},8,["sortKey","sortBy"]),a(f,{modelName:"last_two_month_gross_profit",sortKey:e.value.sortKey,sortBy:e.value.sortBy,onSortTable:t[24]||(t[24]=s=>g("last_two_month_gross_profit"))},{default:l(()=>[r(" GP ($) ")]),_:1},8,["sortKey","sortBy"]),a(f,{modelName:"last_two_month_gross_profit_margin",sortKey:e.value.sortKey,sortBy:e.value.sortBy,onSortTable:t[25]||(t[25]=s=>g("last_two_month_gross_profit_margin"))},{default:l(()=>[r(" GM (%) ")]),_:1},8,["sortKey","sortBy"])])]),o("tbody",Je,[(u(!0),h(U,null,H(i.categories.data,(s,m)=>(u(),h("tr",{key:s.id,class:"divide-x divide-gray-200"},[a(c,{currentIndex:m,totalLength:i.categories.length,inputClass:"text-center"},{default:l(()=>[r(d(i.categories.meta.from+m),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:m,totalLength:i.categories.length,inputClass:"text-left"},{default:l(()=>[r(d(s.name),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:m,totalLength:i.categories.length,inputClass:"text-right"},{default:l(()=>[r(d(s.this_month_revenue.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:m,totalLength:i.categories.length,inputClass:"text-right"},{default:l(()=>[r(d(s.this_month_gross_profit.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:m,totalLength:i.categories.length,inputClass:"text-right"},{default:l(()=>{var y;return[r(d((y=s.this_month_gross_profit_margin)!=null?y:0),1)]}),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:m,totalLength:i.categories.length,inputClass:"text-right"},{default:l(()=>[r(d(s.last_month_revenue.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:m,totalLength:i.categories.length,inputClass:"text-right"},{default:l(()=>[r(d(s.last_month_gross_profit.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:m,totalLength:i.categories.length,inputClass:"text-right"},{default:l(()=>{var y;return[r(d((y=s.last_month_gross_profit_margin)!=null?y:0),1)]}),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:m,totalLength:i.categories.length,inputClass:"text-right"},{default:l(()=>[r(d(s.last_two_month_revenue.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:m,totalLength:i.categories.length,inputClass:"text-right"},{default:l(()=>[r(d(s.last_two_month_gross_profit.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),a(c,{currentIndex:m,totalLength:i.categories.length,inputClass:"text-right"},{default:l(()=>{var y;return[r(d((y=s.last_two_month_gross_profit_margin)!=null?y:0),1)]}),_:2},1032,["currentIndex","totalLength"])]))),128)),i.categories.data.length?p("",!0):(u(),h("tr",Qe,Xe))])]),i.categories.data.length?(u(),K(J,{key:0,links:i.categories.links,meta:i.categories.meta},null,8,["links","meta"])):p("",!0)])])])])]}),_:1})],64))}};export{ct as default};
