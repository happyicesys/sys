import{g as h,Q as G,h as R,f as v,a as o,u as f,w as n,F as U,o as c,Z,b as a,M as L,d as l,c as D,l as p,t as r,k as Y,O as j}from"./app.242e5fba.js";import{_ as q}from"./Authenticated.3a544bed.js";import{_ as N}from"./Button.be945d63.js";import{_ as z}from"./Paginator.d94b1d1a.js";import{_ as F,r as H}from"./SearchInput.af49f9a9.js";import{_ as C}from"./MultiSelect.fb25c41f.js";import{_ as S,a as d}from"./TableData.e7cfe68e.js";import{_}from"./TableHeadSort.5d066413.js";import{r as J}from"./BackspaceIcon.dce1ca77.js";import{r as W}from"./ArrowDownTrayIcon.ba731545.js";import"./keyboard.034c1cc1.js";import"./use-resolve-button-type.ce060f77.js";import"./RectangleStackIcon.e3df9db3.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.1155e6b8.js";const X=a("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Gross Profit by Product ",-1),tt={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},et={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},ot={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},st=a("span",{class:"text-[9px]"},' ("," for multiple) ',-1),nt={key:2},lt=a("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Customer Binded? ",-1),at={key:3},it=a("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),rt={key:4},ut=a("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),dt={key:5},mt=a("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),ct=a("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Location Type ",-1),gt=a("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Current Month ",-1),_t={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ft={class:"mt-3"},pt={class:"flex space-x-1"},ht=a("span",null," Search ",-1),vt=a("span",null," Reset ",-1),yt={key:1,"aria-hidden":"true",class:"mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600",viewBox:"0 0 100 101",fill:"none",xmlns:"http://www.w3.org/2000/svg"},xt=a("path",{d:"M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z",fill:"currentColor"},null,-1),bt=a("path",{d:"M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z",fill:"currentFill"},null,-1),Ct=[xt,bt],St=a("span",null," Export Excel ",-1),Bt={class:"flex flex-col space-y-2"},Lt={class:"text-sm text-gray-700 leading-5 flex space-x-1"},Dt=a("span",null,"Showing",-1),Ft={class:"font-medium"},Kt=a("span",null,"to",-1),wt={class:"font-medium"},Vt=a("span",null,"of",-1),kt={class:"font-medium"},$t=a("span",null,"results",-1),Pt={class:"mt-6 flex flex-col"},Nt={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},It={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll m-2"},Mt={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Ot={class:"bg-gray-100"},Tt={class:"divide-x divide-gray-200"},Gt={class:"divide-x divide-gray-200"},Ut={class:"divide-x divide-gray-200"},jt={class:"bg-white"},At={key:0},Et=a("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Qt=[Et],le={__name:"IndexProduct",props:{categories:Object,categoryGroups:Object,locationTypeOptions:Object,monthOptions:Object,operators:Object,totals:[Array,Object],products:Object},setup(i){const B=i,t=h({categories:[],categoryGroups:[],codes:"",currentMonth:"",customer_code:"",customer_name:"",product_code:"",product_name:"",is_binded_customer:"",location_type_id:"",operator_id:"",sortKey:"",sortBy:!1,numberPerPage:30,visited:!1}),K=h([]),I=h([]),M=h([]),w=h(!1),V=h([]),k=h([]),$=h([]),A=G().props.auth.operatorRole,P=h([]),b=G().props.auth.permissions;R(()=>{t.value.visited=!0,P.value=[{id:30,value:30},{id:50,value:50},{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],t.value.numberPerPage=P.value[0],K.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],I.value=B.categories.data.map(u=>({id:u.id,name:u.name})),M.value=B.categoryGroups.data.map(u=>({id:u.id,name:u.name})),V.value=[{id:"all",value:"All"},...B.locationTypeOptions.data.map(u=>({id:u.id,value:u.name}))],k.value=B.monthOptions.map(u=>({id:u.id,name:u.name})),t.value.currentMonth=k.value[0],$.value=[{id:"all",full_name:"All"},...B.operators.data.map(u=>({id:u.id,full_name:u.full_name}))],t.value.is_binded_customer=A.value?K.value[0]:K.value[1],t.value.location_type_id=V.value[0],t.value.operator_id=$.value[0]});function y(){j.get("/reports/gp/product",{...t.value,categories:t.value.categories.map(u=>u.id),categoryGroups:t.value.categoryGroups.map(u=>u.id),currentMonth:t.value.currentMonth.id,is_binded_customer:t.value.is_binded_customer.id,location_type_id:t.value.location_type_id.id,operator_id:t.value.operator_id.id,numberPerPage:t.value.numberPerPage.id},{preserveState:!0,replace:!0})}function E(){j.get("/reports/gp/product")}function g(u){t.value.sortKey=u,t.value.sortBy=!t.value.sortBy,y()}function Q(){w.value=!0,axios({method:"get",url:"/reports/gp/product/excel",params:{...t.value,categories:t.value.categories.map(u=>u.id),categoryGroups:t.value.categoryGroups.map(u=>u.id),currentMonth:t.value.currentMonth.id,is_binded_customer:t.value.is_binded_customer.id,location_type_id:t.value.location_type_id.id,operator_id:t.value.operator_id.id},responseType:"blob"}).then(u=>{fileDownload(u.data,"UnitCostByProduct_"+moment().format("YYMMDDhhmmss")+".xlsx")}).catch(u=>{console.log(u)}).finally(()=>{w.value=!1})}return(u,e)=>(c(),v(U,null,[o(f(Z),{title:"GP by Product"}),o(q,null,{header:n(()=>[X]),default:n(()=>{var O,T;return[a("div",tt,[a("div",et,[a("div",ot,[o(F,{placeholderStr:"Vend ID",modelValue:t.value.codes,"onUpdate:modelValue":e[0]||(e[0]=s=>t.value.codes=s),onKeyup:e[1]||(e[1]=L(s=>y(),["enter"]))},{default:n(()=>[l(" Vend ID "),st]),_:1},8,["modelValue"]),f(b).includes("admin-access vends")?(c(),D(F,{key:0,placeholderStr:"Cust ID",modelValue:t.value.customer_code,"onUpdate:modelValue":e[2]||(e[2]=s=>t.value.customer_code=s),onKeyup:e[3]||(e[3]=L(s=>y(),["enter"]))},{default:n(()=>[l(" Cust ID ")]),_:1},8,["modelValue"])):p("",!0),f(b).includes("admin-access vends")?(c(),D(F,{key:1,placeholderStr:"Cust Name",modelValue:t.value.customer_name,"onUpdate:modelValue":e[4]||(e[4]=s=>t.value.customer_name=s),onKeyup:e[5]||(e[5]=L(s=>y(),["enter"]))},{default:n(()=>[l(" Cust Name ")]),_:1},8,["modelValue"])):p("",!0),o(F,{placeholderStr:"Product ID",modelValue:t.value.product_code,"onUpdate:modelValue":e[6]||(e[6]=s=>t.value.product_code=s),onKeyup:e[7]||(e[7]=L(s=>y(),["enter"]))},{default:n(()=>[l(" Product ID ")]),_:1},8,["modelValue"]),o(F,{placeholderStr:"Product Name",modelValue:t.value.product_name,"onUpdate:modelValue":e[8]||(e[8]=s=>t.value.product_name=s),onKeyup:e[9]||(e[9]=L(s=>y(),["enter"]))},{default:n(()=>[l(" Product Name ")]),_:1},8,["modelValue"]),f(b).includes("admin-access vends")?(c(),v("div",nt,[lt,o(C,{modelValue:t.value.is_binded_customer,"onUpdate:modelValue":e[10]||(e[10]=s=>t.value.is_binded_customer=s),options:K.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):p("",!0),f(b).includes("admin-access vends")?(c(),v("div",at,[it,o(C,{modelValue:t.value.operator_id,"onUpdate:modelValue":e[11]||(e[11]=s=>t.value.operator_id=s),options:$.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):p("",!0),f(b).includes("admin-access vends")?(c(),v("div",rt,[ut,o(C,{modelValue:t.value.categories,"onUpdate:modelValue":e[12]||(e[12]=s=>t.value.categories=s),options:I.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):p("",!0),f(b).includes("admin-access vends")?(c(),v("div",dt,[mt,o(C,{modelValue:t.value.categoryGroups,"onUpdate:modelValue":e[13]||(e[13]=s=>t.value.categoryGroups=s),options:M.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):p("",!0),a("div",null,[ct,o(C,{modelValue:t.value.location_type_id,"onUpdate:modelValue":e[14]||(e[14]=s=>t.value.location_type_id=s),options:V.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),a("div",null,[gt,o(C,{modelValue:t.value.currentMonth,"onUpdate:modelValue":e[15]||(e[15]=s=>t.value.currentMonth=s),options:k.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),a("div",_t,[a("div",ft,[a("div",pt,[o(N,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:e[16]||(e[16]=s=>y())},{default:n(()=>[o(f(H),{class:"h-4 w-4","aria-hidden":"true"}),ht]),_:1}),o(N,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:e[17]||(e[17]=s=>E())},{default:n(()=>[o(f(J),{class:"h-4 w-4","aria-hidden":"true"}),vt]),_:1}),f(b).includes("export excel")?(c(),D(N,{key:0,class:"inline-flex space-x-1 items-center rounded-md border border-gray-600 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:e[18]||(e[18]=s=>Q())},{default:n(()=>[w.value?p("",!0):(c(),D(f(W),{key:0,class:"h-4 w-4","aria-hidden":"true"})),w.value?(c(),v("svg",yt,Ct)):p("",!0),St]),_:1})):p("",!0)])]),a("div",Bt,[a("p",Lt,[Dt,a("span",Ft,r((O=i.products.meta.from)!=null?O:0),1),Kt,a("span",wt,r((T=i.products.meta.to)!=null?T:0),1),Vt,a("span",kt,r(i.products.meta.total),1),$t]),o(C,{modelValue:t.value.numberPerPage,"onUpdate:modelValue":e[19]||(e[19]=s=>t.value.numberPerPage=s),options:P.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:y},null,8,["modelValue","options"])])])]),a("div",Pt,[a("div",Nt,[a("div",It,[a("table",Mt,[a("thead",Ot,[a("tr",Tt,[o(S,null,{default:n(()=>[l(" # ")]),_:1}),o(_,{modelName:"code",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[20]||(e[20]=s=>g("code"))},{default:n(()=>[l(" ID ")]),_:1},8,["sortKey","sortBy"]),o(S,null,{default:n(()=>[l(" Name ")]),_:1}),o(S,{colspan:"4"},{default:n(()=>[l(" This Month ")]),_:1}),o(S,{colspan:"4"},{default:n(()=>[l(" Last Month ")]),_:1}),o(S,{colspan:"4"},{default:n(()=>[l(" 2 Months Ago ")]),_:1})]),a("tr",Gt,[o(S,{colspan:"3"}),o(_,{modelName:"this_month_count",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[21]||(e[21]=s=>g("this_month_count"))},{default:n(()=>[l(" Sales (Qty) ")]),_:1},8,["sortKey","sortBy"]),o(_,{modelName:"this_month_revenue",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[22]||(e[22]=s=>g("this_month_revenue"))},{default:n(()=>[l(" Sales ($) ")]),_:1},8,["sortKey","sortBy"]),o(_,{modelName:"this_month_gross_profit",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[23]||(e[23]=s=>g("this_month_gross_profit"))},{default:n(()=>[l(" GP ($) ")]),_:1},8,["sortKey","sortBy"]),o(_,{modelName:"this_month_gross_profit_margin",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[24]||(e[24]=s=>g("this_month_gross_profit_margin"))},{default:n(()=>[l(" GM (%) ")]),_:1},8,["sortKey","sortBy"]),o(_,{modelName:"last_month_count",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[25]||(e[25]=s=>g("last_month_count"))},{default:n(()=>[l(" Sales (Qty) ")]),_:1},8,["sortKey","sortBy"]),o(_,{modelName:"last_month_revenue",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[26]||(e[26]=s=>g("last_month_revenue"))},{default:n(()=>[l(" Sales ($) ")]),_:1},8,["sortKey","sortBy"]),o(_,{modelName:"last_month_gross_profit",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[27]||(e[27]=s=>g("last_month_gross_profit"))},{default:n(()=>[l(" GP ($) ")]),_:1},8,["sortKey","sortBy"]),o(_,{modelName:"last_month_gross_profit_margin",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[28]||(e[28]=s=>g("last_month_gross_profit_margin"))},{default:n(()=>[l(" GM (%) ")]),_:1},8,["sortKey","sortBy"]),o(_,{modelName:"last_two_month_count",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[29]||(e[29]=s=>g("last_two_month_count"))},{default:n(()=>[l(" Sales (Qty) ")]),_:1},8,["sortKey","sortBy"]),o(_,{modelName:"last_two_month_revenue",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[30]||(e[30]=s=>g("last_two_month_revenue"))},{default:n(()=>[l(" Sales ($) ")]),_:1},8,["sortKey","sortBy"]),o(_,{modelName:"last_two_month_gross_profit",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[31]||(e[31]=s=>g("last_two_month_gross_profit"))},{default:n(()=>[l(" GP ($) ")]),_:1},8,["sortKey","sortBy"]),o(_,{modelName:"last_two_month_gross_profit_margin",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[32]||(e[32]=s=>g("last_two_month_gross_profit_margin"))},{default:n(()=>[l(" GM (%) ")]),_:1},8,["sortKey","sortBy"])]),a("tr",Ut,[o(S,{colspan:"3"}),o(d,{inputClass:"text-right font-semibold"},{default:n(()=>[l(r(i.totals.this_month_count_total.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)]),_:1}),o(d,{inputClass:"text-right font-semibold"},{default:n(()=>[l(r(i.totals.this_month_revenue_total.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:1}),o(d,{inputClass:"text-right font-semibold"},{default:n(()=>[l(r(i.totals.this_month_gross_profit_total.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:1}),o(d,{inputClass:"text-right font-semibold"},{default:n(()=>[l(r(i.totals.this_month_gross_margin_total.toLocaleString(void 0,{minimumFractionDigits:1,maximumFractionDigits:1})),1)]),_:1}),o(d,{inputClass:"text-right font-semibold"},{default:n(()=>[l(r(i.totals.last_month_count_total.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)]),_:1}),o(d,{inputClass:"text-right font-semibold"},{default:n(()=>[l(r(i.totals.last_month_revenue_total.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:1}),o(d,{inputClass:"text-right font-semibold"},{default:n(()=>[l(r(i.totals.last_month_gross_profit_total.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:1}),o(d,{inputClass:"text-right font-semibold"},{default:n(()=>[l(r(i.totals.last_month_gross_margin_total.toLocaleString(void 0,{minimumFractionDigits:1,maximumFractionDigits:1})),1)]),_:1}),o(d,{inputClass:"text-right font-semibold"},{default:n(()=>[l(r(i.totals.last_two_month_count_total.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)]),_:1}),o(d,{inputClass:"text-right font-semibold"},{default:n(()=>[l(r(i.totals.last_two_month_revenue_total.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:1}),o(d,{inputClass:"text-right font-semibold"},{default:n(()=>[l(r(i.totals.last_two_month_gross_profit_total.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:1}),o(d,{inputClass:"text-right font-semibold"},{default:n(()=>[l(r(i.totals.last_two_month_gross_margin_total.toLocaleString(void 0,{minimumFractionDigits:1,maximumFractionDigits:1})),1)]),_:1})])]),a("tbody",jt,[(c(!0),v(U,null,Y(i.products.data,(s,m)=>(c(),v("tr",{key:s.id,class:"divide-x divide-gray-200"},[o(d,{currentIndex:m,totalLength:i.products.length,inputClass:"text-center"},{default:n(()=>[l(r(i.products.meta.from+m),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.products.length,inputClass:"text-center"},{default:n(()=>[l(r(s.code),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.products.length,inputClass:"text-left"},{default:n(()=>[l(r(s.name),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.products.length,inputClass:"text-right"},{default:n(()=>[l(r(s.this_month_count.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.products.length,inputClass:"text-right"},{default:n(()=>[l(r(s.this_month_revenue.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.products.length,inputClass:"text-right"},{default:n(()=>[l(r(s.this_month_gross_profit.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.products.length,inputClass:"text-right"},{default:n(()=>{var x;return[l(r((x=s.this_month_gross_profit_margin)!=null?x:0),1)]}),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.products.length,inputClass:"text-right"},{default:n(()=>[l(r(s.last_month_count.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.products.length,inputClass:"text-right"},{default:n(()=>[l(r(s.last_month_revenue.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.products.length,inputClass:"text-right"},{default:n(()=>[l(r(s.last_month_gross_profit.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.products.length,inputClass:"text-right"},{default:n(()=>{var x;return[l(r((x=s.last_month_gross_profit_margin)!=null?x:0),1)]}),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.products.length,inputClass:"text-right"},{default:n(()=>[l(r(s.last_two_month_count.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.products.length,inputClass:"text-right"},{default:n(()=>[l(r(s.last_two_month_revenue.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.products.length,inputClass:"text-right"},{default:n(()=>[l(r(s.last_two_month_gross_profit.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.products.length,inputClass:"text-right"},{default:n(()=>{var x;return[l(r((x=s.last_two_month_gross_profit_margin)!=null?x:0),1)]}),_:2},1032,["currentIndex","totalLength"])]))),128)),i.products.data.length?p("",!0):(c(),v("tr",At,Qt))])]),i.products.data.length?(c(),D(z,{key:0,links:i.products.links,meta:i.products.meta},null,8,["links","meta"])):p("",!0)])])])])]}),_:1})],64))}};export{le as default};
