import{_ as D}from"./Authenticated.2b6df163.js";import{_ as E}from"./Button.88df8203.js";import{r as F,a as A,T as f,_ as v,b as H,c as g}from"./TableHeadSort.b84c00bb.js";import{_ as V}from"./SearchInput.fd3895a3.js";import{_ as y}from"./MultiSelect.ee2595ec.js";import{i as h,j as q,o as u,g as c,a as n,b as L,w as l,F as k,H as M,d as t,t as a,m as P,p,c as R,f as r,J as I,n as x}from"./app.30eaa28f.js";import"./open-closed.04462315.js";import"./use-resolve-button-type.3b963a8e.js";import"./RectangleStackIcon.7e887595.js";import"./_plugin-vue_export-helper.cdc0426e.js";const z=t("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines (List) ",-1),Y={class:"m-2 sm:mx-5 sm:my-3 px-2 sm:px-4 lg:px-6"},Q={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-5 px-3 md:px-6 py-6"},W={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},X=r(" Vend ID "),Z=r(" Serial Num "),ee=r(" Temp >> "),te=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Errors? ",-1),oe=r(" Cust ID "),ne=r(" Cust ID Name "),se=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),le=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),ae=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Online? ",-1),re=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Country ",-1),ie={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},de={class:"mt-3"},ue={class:"flex space-x-1"},ce=t("span",null," Search ",-1),me=t("span",null," Reset ",-1),_e={class:"flex flex-col space-y-2"},ge={class:"text-sm text-gray-700 leading-5 flex space-x-1"},pe=t("span",null,"Showing",-1),fe={class:"font-medium"},xe=t("span",null,"to",-1),he={class:"font-medium"},ye=t("span",null,"of",-1),be={class:"font-medium"},ve=t("span",null,"results",-1),Ve={class:"mt-6 flex flex-col"},Ce={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ke={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Se={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Be={class:"bg-gray-100"},Je={class:"divide-x divide-gray-200"},we=r(" # "),Te=r(" Code "),Le=r(" Name "),Pe=r(" Category "),Ie=r(" Error(s) "),Ne=r(" Inventory Status "),je=r(" Temp "),Oe=t("br",null,null,-1),Ke=r(" (Chamber) "),$e=r(" Temp "),Ue=t("br",null,null,-1),Ee=r(" (Evaporator) "),Ge=r(" Balance Stock "),De=r(" Out of Stock SKU "),Fe=r(" Firmware Ver "),Ae=r(" Status "),He=r(" Serial Num "),qe={class:"bg-white"},Me=t("br",null,null,-1),Re=t("br",null,null,-1),ze={class:"flex flex-col"},Ye={class:"font-bold"},Qe={class:"grid grid-cols-[120px_minmax(120px,_1fr)_120px] gap-1"},We={class:"font-semibold"},Xe={class:"text-blue-600 text-sm pl-1"},Ze={class:"pl-1"},et={class:"flex flex-col items-center"},tt=["onClick"],ot={class:"mt-1"},nt={class:"flex flex-col items-center"},st=["onClick"],lt={key:0},at=t("br",null,null,-1),rt={key:0},it=t("br",null,null,-1),dt={class:"grid grid-cols-[90px_minmax(90px,_1fr)_90px] gap-1"},ut={class:"flex flex-col"},ct={class:"font-bold"},mt={key:0},_t={class:"flex flex-col"},gt=t("span",{class:"font-bold"}," Drop Sensor ",-1),pt={key:1,class:"inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200"},ft={class:"flex flex-col"},xt=t("span",{class:"font-bold"}," Fan Speed ",-1),ht={class:"flex flex-col"},yt=t("span",{class:"font-bold"}," Door ",-1),bt={class:"flex flex-col"},vt=t("span",{class:"font-bold"}," Coin ",-1),Vt={key:0},Ct=t("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),kt=[Ct],Ot={__name:"Index",props:{categories:Object,categoryGroups:Object,constTempError:Number,countries:Object,vends:Object,vendChannelErrors:Object},setup(i){const C=i,o=h({code:"",serialNum:"",customer_code:"",customer_name:"",categories:[],categoryGroups:[],country_id:"",tempHigherThan:"",vend_channel_error_id:"",is_online:"",sortKey:"",sortBy:!0,numberPerPage:""}),S=h([]),B=h([]),N=h([]),j=h([]),J=h([]),w=h([]);q(()=>{S.value=[{id:"",desc:"All"},{id:"errors_only",desc:"Errors Only"},...C.vendChannelErrors.data],B.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],o.value.vend_channel_error_id=S.value[0],o.value.numberPerPage=B.value[0],N.value=C.categories.data.map(d=>({id:d.id,name:d.name})),j.value=C.categoryGroups.data.map(d=>({id:d.id,name:d.name})),w.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],o.value.is_online=w.value[1],J.value=[{id:"0",name:"All"},...C.countries.data.map(d=>({id:d.id,name:d.name}))],o.value.country_id=J.value[1]});function T(){I.Inertia.get("/vends",{...o.value,vend_channel_error_id:o.value.vend_channel_error_id.id,categories:o.value.categories.map(d=>d.id),categoryGroups:o.value.categoryGroups.map(d=>d.id),country_id:o.value.country_id.id,is_online:o.value.is_online.id,numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0})}function O(d,s){I.Inertia.get("/vends/"+d+"/temp/"+s)}function G(){I.Inertia.get("/vends")}function b(d){o.value.sortKey=d,o.value.sortBy=!this.filters.sortBy,T()}return(d,s)=>(u(),c(k,null,[n(L(M),{title:"Vending Machines"}),n(D,null,{header:l(()=>[z]),default:l(()=>{var K,$;return[t("div",Y,[t("div",Q,[t("div",W,[n(V,{placeholderStr:"Code",modelValue:o.value.code,"onUpdate:modelValue":s[0]||(s[0]=e=>o.value.code=e)},{default:l(()=>[X]),_:1},8,["modelValue"]),n(V,{placeholderStr:"Serial Num",modelValue:o.value.serialNum,"onUpdate:modelValue":s[1]||(s[1]=e=>o.value.serialNum=e)},{default:l(()=>[Z]),_:1},8,["modelValue"]),n(V,{placeholderStr:"Number",modelValue:o.value.tempHigherThan,"onUpdate:modelValue":s[2]||(s[2]=e=>o.value.tempHigherThan=e)},{default:l(()=>[ee]),_:1},8,["modelValue"]),t("div",null,[te,n(y,{modelValue:o.value.vend_channel_error_id,"onUpdate:modelValue":s[3]||(s[3]=e=>o.value.vend_channel_error_id=e),options:S.value,trackBy:"id",valueProp:"id",label:"desc",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),n(V,{placeholderStr:"Cust ID",modelValue:o.value.customer_code,"onUpdate:modelValue":s[4]||(s[4]=e=>o.value.customer_code=e)},{default:l(()=>[oe]),_:1},8,["modelValue"]),n(V,{placeholderStr:"Cust ID Name",modelValue:o.value.customer_name,"onUpdate:modelValue":s[5]||(s[5]=e=>o.value.customer_name=e)},{default:l(()=>[ne]),_:1},8,["modelValue"]),t("div",null,[se,n(y,{modelValue:o.value.categories,"onUpdate:modelValue":s[6]||(s[6]=e=>o.value.categories=e),options:N.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[le,n(y,{modelValue:o.value.categoryGroups,"onUpdate:modelValue":s[7]||(s[7]=e=>o.value.categoryGroups=e),options:j.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[ae,n(y,{modelValue:o.value.is_online,"onUpdate:modelValue":s[8]||(s[8]=e=>o.value.is_online=e),options:w.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[re,n(y,{modelValue:o.value.country_id,"onUpdate:modelValue":s[9]||(s[9]=e=>o.value.country_id=e),options:J.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),t("div",ie,[t("div",de,[t("div",ue,[n(E,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[10]||(s[10]=e=>T())},{default:l(()=>[n(L(F),{class:"h-4 w-4","aria-hidden":"true"}),ce]),_:1}),n(E,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[11]||(s[11]=e=>G())},{default:l(()=>[n(L(A),{class:"h-4 w-4","aria-hidden":"true"}),me]),_:1})])]),t("div",_e,[t("p",ge,[pe,t("span",fe,a((K=i.vends.meta.from)!=null?K:0),1),xe,t("span",he,a(($=i.vends.meta.to)!=null?$:0),1),ye,t("span",be,a(i.vends.meta.total),1),ve]),n(y,{modelValue:o.value.numberPerPage,"onUpdate:modelValue":s[12]||(s[12]=e=>o.value.numberPerPage=e),options:B.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:T},null,8,["modelValue","options"])])])]),t("div",Ve,[t("div",Ce,[t("div",ke,[t("table",Se,[t("thead",Be,[t("tr",Je,[n(f,null,{default:l(()=>[we]),_:1}),n(v,{modelName:"code",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[13]||(s[13]=e=>b("code"))},{default:l(()=>[Te]),_:1},8,["sortKey","sortBy"]),n(f,null,{default:l(()=>[Le]),_:1}),n(f,null,{default:l(()=>[Pe]),_:1}),n(f,null,{default:l(()=>[Ie]),_:1}),n(f,null,{default:l(()=>[Ne]),_:1}),n(v,{modelName:"temp",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[14]||(s[14]=e=>b("temp"))},{default:l(()=>[je,Oe,Ke]),_:1},8,["sortKey","sortBy"]),n(v,{modelName:"parameter_json->t2",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[15]||(s[15]=e=>b("parameter_json->t2"))},{default:l(()=>[$e,Ue,Ee]),_:1},8,["sortKey","sortBy"]),n(v,{modelName:"vend_channel_totals_json->balancePercent",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[16]||(s[16]=e=>b("vend_channel_totals_json->balancePercent"))},{default:l(()=>[Ge]),_:1},8,["sortKey","sortBy"]),n(v,{modelName:"vend_channel_totals_json->outOfStockSkuPercent",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[17]||(s[17]=e=>b("vend_channel_totals_json->outOfStockSkuPercent"))},{default:l(()=>[De]),_:1},8,["sortKey","sortBy"]),n(f,null,{default:l(()=>[Fe]),_:1}),n(f,null,{default:l(()=>[Ae]),_:1}),n(f,null,{default:l(()=>[He]),_:1})])]),t("tbody",qe,[(u(!0),c(k,null,P(i.vends.data,(e,m)=>(u(),c("tr",{key:e.id,class:"divide-x divide-gray-200"},[n(g,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-center"},{default:l(()=>[r(a(i.vends.meta.from+m),1)]),_:2},1032,["currentIndex","totalLength"]),n(g,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-center"},{default:l(()=>[r(a(e.code),1)]),_:2},1032,["currentIndex","totalLength"]),n(g,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-left"},{default:l(()=>[r(a(e.latestVendBinding&&e.latestVendBinding.customer?e.latestVendBinding.customer.code:null)+" ",1),Me,r(" "+a(e.latestVendBinding&&e.latestVendBinding.customer?e.latestVendBinding.customer.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),n(g,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-left"},{default:l(()=>[r(a(e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.category?e.latestVendBinding.customer.category.name:null)+" ",1),Re,r(" "+a(e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.category&&e.latestVendBinding.customer.category.category_group?e.latestVendBinding.customer.category.category_group.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),n(g,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-center"},{default:l(()=>[(u(!0),c(k,null,P(e.vendChannelErrorLogsJson,_=>(u(),c("span",{class:x(["inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border",[_.is_error_cleared?"bg-green-100 text-green-800":"bg-red-100 text-red-800"]])},[t("div",ze,[t("div",null,[r(" #"+a(_.vend_channel.code)+", ",1),t("span",Ye," ("+a(_.vend_channel_error.code)+") ",1)]),t("div",null,a(_.created_at),1)])],2))),256))]),_:2},1032,["currentIndex","totalLength"]),n(g,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-center"},{default:l(()=>[t("div",Qe,[(u(!0),c(k,null,P(e.vendChannelsJson,(_,U)=>(u(),c("div",{class:x(["inline-flex justify-between items-center rounded px-2.5 py-0.5 text-xs font-medium border min-w-full",[U>0&&String(_.code)[0]!==String(e.vendChannelsJson[U-1].code)[0]?"col-start-1":""]])},[t("div",We," #"+a(_.code)+", ",1),t("div",Xe,a(_.capacity-_.qty)+", ",1),t("div",Ze,a(_.qty)+"/"+a(_.capacity),1)],2))),256))])]),_:2},1032,["currentIndex","totalLength"]),n(g,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-center"},{default:l(()=>[t("div",et,[t("button",{type:"button",class:x(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.temp>-15?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600"]]),onClick:_=>O(e.id,1)},a(e.is_temp_error?"Error":e.temp),11,tt),t("span",ot,a(e.temp_updated_at),1)])]),_:2},1032,["currentIndex","totalLength"]),n(g,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-center"},{default:l(()=>[t("div",nt,[e.parameterJson&&e.parameterJson.t2?(u(),c("button",{key:0,type:"button",class:x(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.temp>-15||e.parameterJson.t2==i.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600"]]),onClick:_=>O(e.id,2)},a(e.parameterJson.t2==i.constTempError?"Error":e.parameterJson.t2/10),11,st)):p("",!0)])]),_:2},1032,["currentIndex","totalLength"]),n(g,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-center"},{default:l(()=>[e.vendChannelTotalsJson?(u(),c("span",lt,[r(a(e.vendChannelTotalsJson.qty)+"/ "+a(e.vendChannelTotalsJson.capacity)+" ",1),at,r(" ("+a(e.vendChannelTotalsJson.balancePercent)+"%) ",1)])):p("",!0)]),_:2},1032,["currentIndex","totalLength"]),n(g,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-center"},{default:l(()=>[e.vendChannelTotalsJson?(u(),c("span",rt,[r(a(e.vendChannelTotalsJson.outOfStockSku)+"/ "+a(e.vendChannelTotalsJson.count)+" ",1),it,r(" ("+a(e.vendChannelTotalsJson.outOfStockSkuPercent)+"%) ",1)])):p("",!0)]),_:2},1032,["currentIndex","totalLength"]),n(g,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-center"},{default:l(()=>[r(a(e.firmware_ver),1)]),_:2},1032,["currentIndex","totalLength"]),n(g,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-center"},{default:l(()=>[t("div",dt,[t("div",{class:x(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.is_online?"bg-green-200":"bg-red-200"]])},[t("div",ut,[t("span",ct,a(e.is_online?"Online":"Offline"),1),e.last_updated_at?(u(),c("span",mt,a(e.last_updated_at),1)):p("",!0)])],2),e.parameterJson&&e.parameterJson.Sensor?(u(),c("div",{key:0,class:x(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.Sensor==1?"bg-green-200":"bg-red-200"]])},[t("div",_t,[gt,t("span",null,a(e.parameterJson.Sensor==1?"Active":"Inactive"),1)])],2)):p("",!0),e.parameterJson&&e.parameterJson.fan?(u(),c("div",pt,[t("div",ft,[xt,t("span",null,a(e.parameterJson.fan),1)])])):p("",!0),e.parameterJson&&e.parameterJson.door?(u(),c("div",{key:2,class:x(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.door=="close"?"bg-green-200":"bg-red-200"]])},[t("div",ht,[yt,t("span",null,a(e.parameterJson.door=="open"?"Open":"Close"),1)])],2)):p("",!0),e.parameterJson&&e.parameterJson.CoinCnt?(u(),c("div",{key:3,class:x(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.CoinCnt>1600?"bg-green-200":"bg-red-200"]])},[t("div",bt,[vt,t("span",null,a((e.parameterJson.CoinCnt/100).toFixed(2)),1)])],2)):p("",!0)])]),_:2},1032,["currentIndex","totalLength"]),n(g,{currentIndex:m,totalLength:i.vends.length,inputClass:"text-center"},{default:l(()=>[r(a(e.serial_num),1)]),_:2},1032,["currentIndex","totalLength"])]))),128)),i.vends.data.length?p("",!0):(u(),c("tr",Vt,kt))])])]),i.vends.data.length?(u(),R(H,{key:0,links:i.vends.links,meta:i.vends.meta},null,8,["links","meta"])):p("",!0)])])])]}),_:1})],64))}};export{Ot as default};
