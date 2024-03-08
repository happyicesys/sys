import{g as p,Q as O,h as E,f as _,a as n,u as v,w as s,F as T,o as m,Z as Q,b as i,M as L,d as a,c as D,l as h,t as r,k as R,O as G}from"./app.c4e47028.js";import{_ as Z}from"./Authenticated.7e90e6af.js";import{_ as M}from"./Button.4631b684.js";import{_ as Y}from"./Paginator.f614a30a.js";import{_ as F,r as q}from"./SearchInput.dcca857e.js";import{_ as C}from"./MultiSelect.0c44488a.js";import{_ as S,a as d}from"./TableData.e3cd56cb.js";import{_ as f}from"./TableHeadSort.6b73435e.js";import{r as z}from"./BackspaceIcon.dae45b54.js";import{r as H}from"./ArrowDownTrayIcon.bf98f728.js";import"./keyboard.58689cfa.js";import"./use-resolve-button-type.ef81a21b.js";import"./RectangleStackIcon.b91bd271.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.dac4e319.js";const J=i("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Gross Profit by VM ",-1),W={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},X={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},tt={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},et=i("span",{class:"text-[9px]"},' ("," for multiple) ',-1),ot={key:2},nt=i("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Customer Binded? ",-1),st={key:3},at=i("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),lt={key:4},it=i("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),rt={key:5},ut=i("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),dt=i("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Location Type ",-1),mt=i("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Current Month ",-1),ct={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},gt={class:"mt-3"},_t={class:"flex space-x-1"},ft=i("span",null," Search ",-1),vt=i("span",null," Reset ",-1),ht={key:1,"aria-hidden":"true",class:"mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600",viewBox:"0 0 100 101",fill:"none",xmlns:"http://www.w3.org/2000/svg"},pt=i("path",{d:"M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z",fill:"currentColor"},null,-1),yt=i("path",{d:"M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z",fill:"currentFill"},null,-1),xt=[pt,yt],bt=i("span",null," Export Excel ",-1),Ct={class:"flex flex-col space-y-2"},St={class:"text-sm text-gray-700 leading-5 flex space-x-1"},Bt=i("span",null,"Showing",-1),Lt={class:"font-medium"},Dt=i("span",null,"to",-1),Ft={class:"font-medium"},Kt=i("span",null,"of",-1),Vt={class:"font-medium"},wt=i("span",null,"results",-1),kt={class:"mt-6 flex flex-col"},$t={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},Pt={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll m-2"},Mt={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Nt={class:"bg-gray-100"},It={class:"divide-x divide-gray-200"},Ot={class:"divide-x divide-gray-200"},Tt={class:"divide-x divide-gray-200"},Gt={class:"bg-white"},Ut={key:0},jt={key:0},At=i("br",null,null,-1),Et={key:1},Qt=i("br",null,null,-1),Rt={key:1},Zt={key:0},Yt=i("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),qt=[Yt],ue={__name:"IndexVm",props:{categories:Object,categoryGroups:Object,locationTypeOptions:Object,monthOptions:Object,operators:Object,totals:[Array,Object],vends:Object},setup(l){const B=l,t=p({categories:[],categoryGroups:[],codes:"",currentMonth:"",customer_code:"",customer_name:"",is_binded_customer:"",location_type_id:"",operator_id:"",product_code:"",product_name:"",sortKey:"",sortBy:!1,numberPerPage:30,visited:!1}),K=p([]),N=p([]),I=p([]),V=p(!1),w=p([]),k=p([]),$=p([]),U=O().props.auth.operatorRole,P=p([]),y=O().props.auth.permissions;E(()=>{t.value.visited=!0,P.value=[{id:30,value:30},{id:50,value:50},{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],t.value.numberPerPage=P.value[0],K.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],N.value=B.categories.data.map(u=>({id:u.id,name:u.name})),I.value=B.categoryGroups.data.map(u=>({id:u.id,name:u.name})),w.value=[{id:"all",value:"All"},...B.locationTypeOptions.data.map(u=>({id:u.id,value:u.name}))],k.value=B.monthOptions.map(u=>({id:u.id,name:u.name})),t.value.currentMonth=k.value[0],$.value=[{id:"all",full_name:"All"},...B.operators.data.map(u=>({id:u.id,full_name:u.full_name}))],t.value.is_binded_customer=U.value?K.value[0]:K.value[1],t.value.location_type_id=w.value[0],t.value.operator_id=$.value[0]});function x(){G.get("/reports/gp/vend",{...t.value,categories:t.value.categories.map(u=>u.id),categoryGroups:t.value.categoryGroups.map(u=>u.id),currentMonth:t.value.currentMonth.id,is_binded_customer:t.value.is_binded_customer.id,location_type_id:t.value.location_type_id.id,operator_id:t.value.operator_id.id,numberPerPage:t.value.numberPerPage.id},{preserveState:!0,replace:!0})}function j(){G.get("/reports/gp/vend")}function g(u){t.value.sortKey=u,t.value.sortBy=!t.value.sortBy,x()}function A(){V.value=!0,axios({method:"get",url:"/reports/gp/vend/excel",params:{...t.value,categories:t.value.categories.map(u=>u.id),categoryGroups:t.value.categoryGroups.map(u=>u.id),currentMonth:t.value.currentMonth.id,is_binded_customer:t.value.is_binded_customer.id,location_type_id:t.value.location_type_id.id,operator_id:t.value.operator_id.id},responseType:"blob"}).then(u=>{fileDownload(u.data,"UnitCostByVm_"+moment().format("YYMMDDhhmmss")+".xlsx")}).catch(u=>{console.log(u)}).finally(()=>{V.value=!1})}return(u,e)=>(m(),_(T,null,[n(v(Q),{title:"GP by VM"}),n(Z,null,{header:s(()=>[J]),default:s(()=>[i("div",W,[i("div",X,[i("div",tt,[n(F,{placeholderStr:"Vend ID",modelValue:t.value.codes,"onUpdate:modelValue":e[0]||(e[0]=o=>t.value.codes=o),onKeyup:e[1]||(e[1]=L(o=>x(),["enter"]))},{default:s(()=>[a(" Vend ID "),et]),_:1},8,["modelValue"]),v(y).includes("admin-access vends")?(m(),D(F,{key:0,placeholderStr:"Cust ID",modelValue:t.value.customer_code,"onUpdate:modelValue":e[2]||(e[2]=o=>t.value.customer_code=o),onKeyup:e[3]||(e[3]=L(o=>x(),["enter"]))},{default:s(()=>[a(" Cust ID ")]),_:1},8,["modelValue"])):h("",!0),v(y).includes("admin-access vends")?(m(),D(F,{key:1,placeholderStr:"Cust Name",modelValue:t.value.customer_name,"onUpdate:modelValue":e[4]||(e[4]=o=>t.value.customer_name=o),onKeyup:e[5]||(e[5]=L(o=>x(),["enter"]))},{default:s(()=>[a(" Cust Name ")]),_:1},8,["modelValue"])):h("",!0),n(F,{placeholderStr:"Product ID",modelValue:t.value.product_code,"onUpdate:modelValue":e[6]||(e[6]=o=>t.value.product_code=o),onKeyup:e[7]||(e[7]=L(o=>x(),["enter"]))},{default:s(()=>[a(" Product ID ")]),_:1},8,["modelValue"]),n(F,{placeholderStr:"Product Name",modelValue:t.value.product_name,"onUpdate:modelValue":e[8]||(e[8]=o=>t.value.product_name=o),onKeyup:e[9]||(e[9]=L(o=>x(),["enter"]))},{default:s(()=>[a(" Product Name ")]),_:1},8,["modelValue"]),v(y).includes("admin-access vends")?(m(),_("div",ot,[nt,n(C,{modelValue:t.value.is_binded_customer,"onUpdate:modelValue":e[10]||(e[10]=o=>t.value.is_binded_customer=o),options:K.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):h("",!0),v(y).includes("admin-access vends")?(m(),_("div",st,[at,n(C,{modelValue:t.value.operator_id,"onUpdate:modelValue":e[11]||(e[11]=o=>t.value.operator_id=o),options:$.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):h("",!0),v(y).includes("admin-access vends")?(m(),_("div",lt,[it,n(C,{modelValue:t.value.categories,"onUpdate:modelValue":e[12]||(e[12]=o=>t.value.categories=o),options:N.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):h("",!0),v(y).includes("admin-access vends")?(m(),_("div",rt,[ut,n(C,{modelValue:t.value.categoryGroups,"onUpdate:modelValue":e[13]||(e[13]=o=>t.value.categoryGroups=o),options:I.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):h("",!0),i("div",null,[dt,n(C,{modelValue:t.value.location_type_id,"onUpdate:modelValue":e[14]||(e[14]=o=>t.value.location_type_id=o),options:w.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),i("div",null,[mt,n(C,{modelValue:t.value.currentMonth,"onUpdate:modelValue":e[15]||(e[15]=o=>t.value.currentMonth=o),options:k.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),i("div",ct,[i("div",gt,[i("div",_t,[n(M,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:e[16]||(e[16]=o=>x())},{default:s(()=>[n(v(q),{class:"h-4 w-4","aria-hidden":"true"}),ft]),_:1}),n(M,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:e[17]||(e[17]=o=>j())},{default:s(()=>[n(v(z),{class:"h-4 w-4","aria-hidden":"true"}),vt]),_:1}),v(y).includes("export excel")?(m(),D(M,{key:0,class:"inline-flex space-x-1 items-center rounded-md border border-gray-600 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:e[18]||(e[18]=o=>A())},{default:s(()=>[V.value?h("",!0):(m(),D(v(H),{key:0,class:"h-4 w-4","aria-hidden":"true"})),V.value?(m(),_("svg",ht,xt)):h("",!0),bt]),_:1})):h("",!0)])]),i("div",Ct,[i("p",St,[Bt,i("span",Lt,r(l.vends&&l.vends.meta&&l.vends.meta.from?l.vends.meta.from:0),1),Dt,i("span",Ft,r(l.vends&&l.vends.meta&&l.vends.meta.to?l.vends.meta.to:0),1),Kt,i("span",Vt,r(l.vends&&l.vends.meta&&l.vends.meta.total?l.vends.meta.total:0),1),wt]),n(C,{modelValue:t.value.numberPerPage,"onUpdate:modelValue":e[19]||(e[19]=o=>t.value.numberPerPage=o),options:P.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:x},null,8,["modelValue","options"])])])]),i("div",kt,[i("div",$t,[i("div",Pt,[i("table",Mt,[i("thead",Nt,[i("tr",It,[n(S,null,{default:s(()=>[a(" # ")]),_:1}),n(f,{modelName:"code",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[20]||(e[20]=o=>g("code"))},{default:s(()=>[a(" ID ")]),_:1},8,["sortKey","sortBy"]),n(S,null,{default:s(()=>[a(" Name ")]),_:1}),n(S,{colspan:"4"},{default:s(()=>[a(" This Month ")]),_:1}),n(S,{colspan:"4"},{default:s(()=>[a(" Last Month ")]),_:1}),n(S,{colspan:"4"},{default:s(()=>[a(" 2 Months Ago ")]),_:1})]),i("tr",Ot,[n(S,{colspan:"3"}),n(f,{modelName:"this_month_count",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[21]||(e[21]=o=>g("this_month_count"))},{default:s(()=>[a(" Sales (Qty) ")]),_:1},8,["sortKey","sortBy"]),n(f,{modelName:"this_month_revenue",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[22]||(e[22]=o=>g("this_month_revenue"))},{default:s(()=>[a(" Sales ($) ")]),_:1},8,["sortKey","sortBy"]),n(f,{modelName:"this_month_gross_profit",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[23]||(e[23]=o=>g("this_month_gross_profit"))},{default:s(()=>[a(" GP ($) ")]),_:1},8,["sortKey","sortBy"]),n(f,{modelName:"this_month_gross_profit_margin",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[24]||(e[24]=o=>g("this_month_gross_profit_margin"))},{default:s(()=>[a(" GM (%) ")]),_:1},8,["sortKey","sortBy"]),n(f,{modelName:"last_month_count",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[25]||(e[25]=o=>g("last_month_count"))},{default:s(()=>[a(" Sales (Qty) ")]),_:1},8,["sortKey","sortBy"]),n(f,{modelName:"last_month_revenue",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[26]||(e[26]=o=>g("last_month_revenue"))},{default:s(()=>[a(" Sales ($) ")]),_:1},8,["sortKey","sortBy"]),n(f,{modelName:"last_month_gross_profit",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[27]||(e[27]=o=>g("last_month_gross_profit"))},{default:s(()=>[a(" GP ($) ")]),_:1},8,["sortKey","sortBy"]),n(f,{modelName:"last_month_gross_profit_margin",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[28]||(e[28]=o=>g("last_month_gross_profit_margin"))},{default:s(()=>[a(" GM (%) ")]),_:1},8,["sortKey","sortBy"]),n(f,{modelName:"last_two_month_count",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[29]||(e[29]=o=>g("last_two_month_count"))},{default:s(()=>[a(" Sales (Qty) ")]),_:1},8,["sortKey","sortBy"]),n(f,{modelName:"last_two_month_revenue",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[30]||(e[30]=o=>g("last_two_month_revenue"))},{default:s(()=>[a(" Sales ($) ")]),_:1},8,["sortKey","sortBy"]),n(f,{modelName:"last_two_month_gross_profit",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[31]||(e[31]=o=>g("last_two_month_gross_profit"))},{default:s(()=>[a(" GP ($) ")]),_:1},8,["sortKey","sortBy"]),n(f,{modelName:"last_two_month_gross_profit_margin",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[32]||(e[32]=o=>g("last_two_month_gross_profit_margin"))},{default:s(()=>[a(" GM (%) ")]),_:1},8,["sortKey","sortBy"])]),i("tr",Tt,[n(S,{colspan:"3"}),n(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(r(l.totals.this_month_count_total.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)]),_:1}),n(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(r(l.totals.this_month_revenue_total.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:1}),n(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(r(l.totals.this_month_gross_profit_total.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:1}),n(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(r(l.totals.this_month_gross_margin_total.toLocaleString(void 0,{minimumFractionDigits:1,maximumFractionDigits:1})),1)]),_:1}),n(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(r(l.totals.last_month_count_total.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)]),_:1}),n(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(r(l.totals.last_month_revenue_total.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:1}),n(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(r(l.totals.last_month_gross_profit_total.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:1}),n(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(r(l.totals.last_month_gross_margin_total.toLocaleString(void 0,{minimumFractionDigits:1,maximumFractionDigits:1})),1)]),_:1}),n(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(r(l.totals.last_two_month_count_total.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)]),_:1}),n(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(r(l.totals.last_two_month_revenue_total.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:1}),n(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(r(l.totals.last_two_month_gross_profit_total.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:1}),n(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(r(l.totals.last_two_month_gross_margin_total.toLocaleString(void 0,{minimumFractionDigits:1,maximumFractionDigits:1})),1)]),_:1})])]),i("tbody",Gt,[(m(!0),_(T,null,R(l.vends.data,(o,c)=>(m(),_("tr",{key:o.id,class:"divide-x divide-gray-200"},[n(d,{currentIndex:c,totalLength:l.vends.length,inputClass:"text-center"},{default:s(()=>[a(r(l.vends.meta.from+c),1)]),_:2},1032,["currentIndex","totalLength"]),n(d,{currentIndex:c,totalLength:l.vends.length,inputClass:"text-center"},{default:s(()=>[a(r(o.code),1)]),_:2},1032,["currentIndex","totalLength"]),n(d,{currentIndex:c,totalLength:l.vends.length,inputClass:"text-left"},{default:s(()=>[o.customer_code||o.customer_name?(m(),_("span",Ut,[v(y).includes("admin-access vends")?(m(),_("span",jt,[a(r(o.customer_code)+" ",1),At,a(" "+r(o.customer_name),1)])):(m(),_("span",Et,[a(r(o.customer_code)+" ",1),Qt,a(" "+r(o.customer_name),1)]))])):(m(),_("span",Rt,r(o.name),1))]),_:2},1032,["currentIndex","totalLength"]),n(d,{currentIndex:c,totalLength:l.vends.length,inputClass:"text-right"},{default:s(()=>[a(r(o.this_month_count.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)]),_:2},1032,["currentIndex","totalLength"]),n(d,{currentIndex:c,totalLength:l.vends.length,inputClass:"text-right"},{default:s(()=>[a(r(o.this_month_revenue.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),n(d,{currentIndex:c,totalLength:l.vends.length,inputClass:"text-right"},{default:s(()=>[a(r(o.this_month_gross_profit.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),n(d,{currentIndex:c,totalLength:l.vends.length,inputClass:"text-right"},{default:s(()=>{var b;return[a(r((b=o.this_month_gross_profit_margin)!=null?b:0),1)]}),_:2},1032,["currentIndex","totalLength"]),n(d,{currentIndex:c,totalLength:l.vends.length,inputClass:"text-right"},{default:s(()=>[a(r(o.last_month_count.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)]),_:2},1032,["currentIndex","totalLength"]),n(d,{currentIndex:c,totalLength:l.vends.length,inputClass:"text-right"},{default:s(()=>[a(r(o.last_month_revenue.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),n(d,{currentIndex:c,totalLength:l.vends.length,inputClass:"text-right"},{default:s(()=>[a(r(o.last_month_gross_profit.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),n(d,{currentIndex:c,totalLength:l.vends.length,inputClass:"text-right"},{default:s(()=>{var b;return[a(r((b=o.last_month_gross_profit_margin)!=null?b:0),1)]}),_:2},1032,["currentIndex","totalLength"]),n(d,{currentIndex:c,totalLength:l.vends.length,inputClass:"text-right"},{default:s(()=>[a(r(o.last_two_month_count.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)]),_:2},1032,["currentIndex","totalLength"]),n(d,{currentIndex:c,totalLength:l.vends.length,inputClass:"text-right"},{default:s(()=>[a(r(o.last_two_month_revenue.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),n(d,{currentIndex:c,totalLength:l.vends.length,inputClass:"text-right"},{default:s(()=>[a(r(o.last_two_month_gross_profit.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),n(d,{currentIndex:c,totalLength:l.vends.length,inputClass:"text-right"},{default:s(()=>{var b;return[a(r((b=o.last_two_month_gross_profit_margin)!=null?b:0),1)]}),_:2},1032,["currentIndex","totalLength"])]))),128)),l.vends.data.length?h("",!0):(m(),_("tr",Zt,qt))])]),l.vends.data.length?(m(),D(Y,{key:0,links:l.vends.links,meta:l.vends.meta},null,8,["links","meta"])):h("",!0)])])])])]),_:1})],64))}};export{ue as default};
