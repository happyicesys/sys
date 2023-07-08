import{h as v,K as G,j as Z,f as y,a as o,u as f,w as s,F as U,o as c,Z as Q,b as l,P as L,d as a,c as D,m as p,t as u,l as Y,O as j}from"./app.1f3b9bf1.js";import{_ as q}from"./Authenticated.c6c7dbae.js";import{_ as N}from"./Button.52c6fc2d.js";import{_ as S,b as d,a as z}from"./TableData.cfc15ede.js";import{_ as F,r as H}from"./SearchInput.bbdee196.js";import{_ as C}from"./MultiSelect.5fa2f578.js";import{_}from"./TableHeadSort.2e5b3d14.js";import{r as J}from"./BackspaceIcon.7b2b9ec7.js";import{r as W}from"./ArrowDownTrayIcon.f90bd3d6.js";import"./open-closed.56f1f1ae.js";import"./use-resolve-button-type.9be7fef7.js";import"./RectangleStackIcon.92468871.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.c07ee110.js";const X=l("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Gross Profit by Location Type ",-1),tt={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},et={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},ot={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},nt=l("span",{class:"text-[9px]"},' ("," for multiple) ',-1),st={key:2},at=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Customer Binded? ",-1),lt={key:3},it=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),rt={key:4},ut=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),dt={key:5},mt=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),ct=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Location Type ",-1),gt=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Current Month ",-1),_t={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ft={class:"mt-3"},pt={class:"flex space-x-1"},vt=l("span",null," Search ",-1),yt=l("span",null," Reset ",-1),ht={key:1,"aria-hidden":"true",class:"mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600",viewBox:"0 0 100 101",fill:"none",xmlns:"http://www.w3.org/2000/svg"},xt=l("path",{d:"M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z",fill:"currentColor"},null,-1),bt=l("path",{d:"M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z",fill:"currentFill"},null,-1),Ct=[xt,bt],St=l("span",null," Export Excel ",-1),Bt={class:"flex flex-col space-y-2"},Lt={class:"text-sm text-gray-700 leading-5 flex space-x-1"},Dt=l("span",null,"Showing",-1),Ft={class:"font-medium"},Kt=l("span",null,"to",-1),wt={class:"font-medium"},Vt=l("span",null,"of",-1),kt={class:"font-medium"},$t=l("span",null,"results",-1),Pt={class:"mt-6 flex flex-col"},Nt={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},Tt={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll m-2"},It={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Mt={class:"bg-gray-100"},Ot={class:"divide-x divide-gray-200"},Gt={class:"divide-x divide-gray-200"},Ut={class:"divide-x divide-gray-200"},jt={class:"bg-white"},At={key:0},Et=l("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Rt=[Et],se={__name:"IndexLocationType",props:{categories:Object,categoryGroups:Object,locationTypeOptions:Object,monthOptions:Object,operators:Object,totals:[Array,Object],locationTypes:Object},setup(i){const B=i,t=v({categories:[],categoryGroups:[],codes:"",currentMonth:"",customer_code:"",customer_name:"",is_binded_customer:"",location_type_id:"",operator_id:"",sortKey:"",sortBy:!1,numberPerPage:100,visited:!1}),K=v([]),T=v([]),I=v([]),w=v(!1),V=v([]),k=v([]),$=v([]),A=G().props.auth.operatorRole,P=v([]),b=G().props.auth.permissions;Z(()=>{t.value.visited=!0,P.value=[{id:50,value:50},{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],t.value.numberPerPage=P.value[0],K.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],T.value=B.categories.data.map(r=>({id:r.id,name:r.name})),I.value=B.categoryGroups.data.map(r=>({id:r.id,name:r.name})),V.value=[{id:"all",value:"All"},...B.locationTypeOptions.data.map(r=>({id:r.id,value:r.name}))],k.value=B.monthOptions.map(r=>({id:r.id,name:r.name})),t.value.currentMonth=k.value[0],$.value=[{id:"all",full_name:"All"},...B.operators.data.map(r=>({id:r.id,full_name:r.full_name}))],t.value.is_binded_customer=A.value?K.value[0]:K.value[1],t.value.location_type_id=V.value[0],t.value.operator_id=$.value[0]});function h(){j.get("/reports/gp/location-type",{...t.value,categories:t.value.categories.map(r=>r.id),categoryGroups:t.value.categoryGroups.map(r=>r.id),currentMonth:t.value.currentMonth.id,is_binded_customer:t.value.is_binded_customer.id,location_type_id:t.value.location_type_id.id,operator_id:t.value.operator_id.id,numberPerPage:t.value.numberPerPage.id},{preserveState:!0,replace:!0})}function E(){j.get("/reports/gp/location-type")}function g(r){t.value.sortKey=r,t.value.sortBy=!t.value.sortBy,h()}function R(){w.value=!0,axios({method:"get",url:"/reports/gp/location-type/excel",params:{...t.value,categories:t.value.categories.map(r=>r.id),categoryGroups:t.value.categoryGroups.map(r=>r.id),currentMonth:t.value.currentMonth.id,is_binded_customer:t.value.is_binded_customer.id,location_type_id:t.value.location_type_id.id,operator_id:t.value.operator_id.id},responseType:"blob"}).then(r=>{fileDownload(r.data,"UnitCostByLocationType_"+moment().format("YYMMDDhhmmss")+".xlsx")}).catch(r=>{console.log(r)}).finally(()=>{w.value=!1})}return(r,e)=>(c(),y(U,null,[o(f(Q),{title:"GP by Location Type"}),o(q,null,{header:s(()=>[X]),default:s(()=>{var M,O;return[l("div",tt,[l("div",et,[l("div",ot,[o(F,{placeholderStr:"Vend ID",modelValue:t.value.codes,"onUpdate:modelValue":e[0]||(e[0]=n=>t.value.codes=n),onKeyup:e[1]||(e[1]=L(n=>h(),["enter"]))},{default:s(()=>[a(" Vend ID "),nt]),_:1},8,["modelValue"]),f(b).includes("admin-access vends")?(c(),D(F,{key:0,placeholderStr:"Cust ID",modelValue:t.value.customer_code,"onUpdate:modelValue":e[2]||(e[2]=n=>t.value.customer_code=n),onKeyup:e[3]||(e[3]=L(n=>h(),["enter"]))},{default:s(()=>[a(" Cust ID ")]),_:1},8,["modelValue"])):p("",!0),f(b).includes("admin-access vends")?(c(),D(F,{key:1,placeholderStr:"Cust Name",modelValue:t.value.customer_name,"onUpdate:modelValue":e[4]||(e[4]=n=>t.value.customer_name=n),onKeyup:e[5]||(e[5]=L(n=>h(),["enter"]))},{default:s(()=>[a(" Cust Name ")]),_:1},8,["modelValue"])):p("",!0),o(F,{placeholderStr:"Product ID",modelValue:t.value.product_code,"onUpdate:modelValue":e[6]||(e[6]=n=>t.value.product_code=n),onKeyup:e[7]||(e[7]=L(n=>h(),["enter"]))},{default:s(()=>[a(" Product ID ")]),_:1},8,["modelValue"]),o(F,{placeholderStr:"Product Name",modelValue:t.value.product_name,"onUpdate:modelValue":e[8]||(e[8]=n=>t.value.product_name=n),onKeyup:e[9]||(e[9]=L(n=>h(),["enter"]))},{default:s(()=>[a(" Product Name ")]),_:1},8,["modelValue"]),f(b).includes("admin-access vends")?(c(),y("div",st,[at,o(C,{modelValue:t.value.is_binded_customer,"onUpdate:modelValue":e[10]||(e[10]=n=>t.value.is_binded_customer=n),options:K.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):p("",!0),f(b).includes("admin-access vends")?(c(),y("div",lt,[it,o(C,{modelValue:t.value.operator_id,"onUpdate:modelValue":e[11]||(e[11]=n=>t.value.operator_id=n),options:$.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):p("",!0),f(b).includes("admin-access vends")?(c(),y("div",rt,[ut,o(C,{modelValue:t.value.categories,"onUpdate:modelValue":e[12]||(e[12]=n=>t.value.categories=n),options:T.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):p("",!0),f(b).includes("admin-access vends")?(c(),y("div",dt,[mt,o(C,{modelValue:t.value.categoryGroups,"onUpdate:modelValue":e[13]||(e[13]=n=>t.value.categoryGroups=n),options:I.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):p("",!0),l("div",null,[ct,o(C,{modelValue:t.value.location_type_id,"onUpdate:modelValue":e[14]||(e[14]=n=>t.value.location_type_id=n),options:V.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),l("div",null,[gt,o(C,{modelValue:t.value.currentMonth,"onUpdate:modelValue":e[15]||(e[15]=n=>t.value.currentMonth=n),options:k.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),l("div",_t,[l("div",ft,[l("div",pt,[o(N,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:e[16]||(e[16]=n=>h())},{default:s(()=>[o(f(H),{class:"h-4 w-4","aria-hidden":"true"}),vt]),_:1}),o(N,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:e[17]||(e[17]=n=>E())},{default:s(()=>[o(f(J),{class:"h-4 w-4","aria-hidden":"true"}),yt]),_:1}),f(b).includes("export excel")?(c(),D(N,{key:0,class:"inline-flex space-x-1 items-center rounded-md border border-gray-600 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:e[18]||(e[18]=n=>R())},{default:s(()=>[w.value?p("",!0):(c(),D(f(W),{key:0,class:"h-4 w-4","aria-hidden":"true"})),w.value?(c(),y("svg",ht,Ct)):p("",!0),St]),_:1})):p("",!0)])]),l("div",Bt,[l("p",Lt,[Dt,l("span",Ft,u((M=i.locationTypes.meta.from)!=null?M:0),1),Kt,l("span",wt,u((O=i.locationTypes.meta.to)!=null?O:0),1),Vt,l("span",kt,u(i.locationTypes.meta.total),1),$t]),o(C,{modelValue:t.value.numberPerPage,"onUpdate:modelValue":e[19]||(e[19]=n=>t.value.numberPerPage=n),options:P.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:h},null,8,["modelValue","options"])])])]),l("div",Pt,[l("div",Nt,[l("div",Tt,[l("table",It,[l("thead",Mt,[l("tr",Ot,[o(S,null,{default:s(()=>[a(" # ")]),_:1}),o(_,{colspan:"2",modelName:"name",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[20]||(e[20]=n=>g("name"))},{default:s(()=>[a(" Name ")]),_:1},8,["sortKey","sortBy"]),o(S,{colspan:"4"},{default:s(()=>[a(" This Month ")]),_:1}),o(S,{colspan:"4"},{default:s(()=>[a(" Last Month ")]),_:1}),o(S,{colspan:"4"},{default:s(()=>[a(" 2 Months Ago ")]),_:1})]),l("tr",Gt,[o(S,{colspan:"3"}),o(_,{modelName:"this_month_count",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[21]||(e[21]=n=>g("this_month_count"))},{default:s(()=>[a(" Sales (Qty) ")]),_:1},8,["sortKey","sortBy"]),o(_,{modelName:"this_month_revenue",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[22]||(e[22]=n=>g("this_month_revenue"))},{default:s(()=>[a(" Sales ($) ")]),_:1},8,["sortKey","sortBy"]),o(_,{modelName:"this_month_gross_profit",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[23]||(e[23]=n=>g("this_month_gross_profit"))},{default:s(()=>[a(" GP ($) ")]),_:1},8,["sortKey","sortBy"]),o(_,{modelName:"this_month_gross_profit_margin",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[24]||(e[24]=n=>g("this_month_gross_profit_margin"))},{default:s(()=>[a(" GM (%) ")]),_:1},8,["sortKey","sortBy"]),o(_,{modelName:"last_month_count",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[25]||(e[25]=n=>g("last_month_count"))},{default:s(()=>[a(" Sales (Qty) ")]),_:1},8,["sortKey","sortBy"]),o(_,{modelName:"last_month_revenue",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[26]||(e[26]=n=>g("last_month_revenue"))},{default:s(()=>[a(" Sales ($) ")]),_:1},8,["sortKey","sortBy"]),o(_,{modelName:"last_month_gross_profit",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[27]||(e[27]=n=>g("last_month_gross_profit"))},{default:s(()=>[a(" GP ($) ")]),_:1},8,["sortKey","sortBy"]),o(_,{modelName:"last_month_gross_profit_margin",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[28]||(e[28]=n=>g("last_month_gross_profit_margin"))},{default:s(()=>[a(" GM (%) ")]),_:1},8,["sortKey","sortBy"]),o(_,{modelName:"last_two_month_count",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[29]||(e[29]=n=>g("last_two_month_count"))},{default:s(()=>[a(" Sales (Qty) ")]),_:1},8,["sortKey","sortBy"]),o(_,{modelName:"last_two_month_revenue",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[30]||(e[30]=n=>g("last_two_month_revenue"))},{default:s(()=>[a(" Sales ($) ")]),_:1},8,["sortKey","sortBy"]),o(_,{modelName:"last_two_month_gross_profit",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[31]||(e[31]=n=>g("last_two_month_gross_profit"))},{default:s(()=>[a(" GP ($) ")]),_:1},8,["sortKey","sortBy"]),o(_,{modelName:"last_two_month_gross_profit_margin",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:e[32]||(e[32]=n=>g("last_two_month_gross_profit_margin"))},{default:s(()=>[a(" GM (%) ")]),_:1},8,["sortKey","sortBy"])]),l("tr",Ut,[o(S,{colspan:"3"}),o(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(u(i.totals.this_month_count_total.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)]),_:1}),o(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(u(i.totals.this_month_revenue_total.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:1}),o(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(u(i.totals.this_month_gross_profit_total.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:1}),o(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(u(i.totals.this_month_gross_margin_total.toLocaleString(void 0,{minimumFractionDigits:1,maximumFractionDigits:1})),1)]),_:1}),o(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(u(i.totals.last_month_count_total.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)]),_:1}),o(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(u(i.totals.last_month_revenue_total.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:1}),o(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(u(i.totals.last_month_gross_profit_total.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:1}),o(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(u(i.totals.last_month_gross_margin_total.toLocaleString(void 0,{minimumFractionDigits:1,maximumFractionDigits:1})),1)]),_:1}),o(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(u(i.totals.last_two_month_count_total.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)]),_:1}),o(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(u(i.totals.last_two_month_revenue_total.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:1}),o(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(u(i.totals.last_two_month_gross_profit_total.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:1}),o(d,{inputClass:"text-right font-semibold"},{default:s(()=>[a(u(i.totals.last_two_month_gross_margin_total.toLocaleString(void 0,{minimumFractionDigits:1,maximumFractionDigits:1})),1)]),_:1})])]),l("tbody",jt,[(c(!0),y(U,null,Y(i.locationTypes.data,(n,m)=>(c(),y("tr",{key:n.id,class:"divide-x divide-gray-200"},[o(d,{currentIndex:m,totalLength:i.locationTypes.length,inputClass:"text-center"},{default:s(()=>[a(u(i.locationTypes.meta.from+m),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{colspan:"2",currentIndex:m,totalLength:i.locationTypes.length,inputClass:"text-left"},{default:s(()=>[a(u(n.name),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.locationTypes.length,inputClass:"text-right"},{default:s(()=>[a(u(n.this_month_count.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.locationTypes.length,inputClass:"text-right"},{default:s(()=>[a(u(n.this_month_revenue.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.locationTypes.length,inputClass:"text-right"},{default:s(()=>[a(u(n.this_month_gross_profit.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.locationTypes.length,inputClass:"text-right"},{default:s(()=>{var x;return[a(u((x=n.this_month_gross_profit_margin)!=null?x:0),1)]}),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.locationTypes.length,inputClass:"text-right"},{default:s(()=>[a(u(n.last_month_count.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.locationTypes.length,inputClass:"text-right"},{default:s(()=>[a(u(n.last_month_revenue.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.locationTypes.length,inputClass:"text-right"},{default:s(()=>[a(u(n.last_month_gross_profit.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.locationTypes.length,inputClass:"text-right"},{default:s(()=>{var x;return[a(u((x=n.last_month_gross_profit_margin)!=null?x:0),1)]}),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.locationTypes.length,inputClass:"text-right"},{default:s(()=>[a(u(n.last_two_month_count.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.locationTypes.length,inputClass:"text-right"},{default:s(()=>[a(u(n.last_two_month_revenue.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.locationTypes.length,inputClass:"text-right"},{default:s(()=>[a(u(n.last_two_month_gross_profit.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),_:2},1032,["currentIndex","totalLength"]),o(d,{currentIndex:m,totalLength:i.locationTypes.length,inputClass:"text-right"},{default:s(()=>{var x;return[a(u((x=n.last_two_month_gross_profit_margin)!=null?x:0),1)]}),_:2},1032,["currentIndex","totalLength"])]))),128)),i.locationTypes.data.length?p("",!0):(c(),y("tr",At,Rt))])]),i.locationTypes.data.length?(c(),D(z,{key:0,links:i.locationTypes.links,meta:i.locationTypes.meta},null,8,["links","meta"])):p("",!0)])])])])]}),_:1})],64))}};export{se as default};
