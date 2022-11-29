import{_ as G}from"./Authenticated.b7afb3d5.js";import{_ as U}from"./Button.c7d5f41d.js";import{r as F,a as A,T as x,_ as b,b as D,c as p}from"./TableHeadSort.cccbebdd.js";import{_ as k}from"./SearchInput.2a4fcabe.js";import{_ as y}from"./MultiSelect.52976e8a.js";import{i as v,j as H,o as u,g as c,a as o,b as P,w as l,F as V,H as q,d as t,t as r,m as w,p as g,c as M,f as a,J as N,n as f}from"./app.76edae15.js";import"./open-closed.a637f55d.js";import"./use-resolve-button-type.01cba125.js";import"./RectangleStackIcon.01e441d8.js";import"./_plugin-vue_export-helper.cdc0426e.js";const R=t("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines (List) ",-1),z={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},Y={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Q={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},W=a(" Vend ID "),X=a(" Serial Num "),Z=a(" Temp >> "),ee=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Errors? ",-1),te=a(" Cust ID "),se=a(" Cust Name "),oe=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),ne=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),le=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Online? ",-1),ae=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Customer Binded? ",-1),re={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},de={class:"mt-3"},ie={class:"flex space-x-1"},ue=t("span",null," Search ",-1),ce=t("span",null," Reset ",-1),me={class:"flex flex-col space-y-2"},_e={class:"text-sm text-gray-700 leading-5 flex space-x-1"},pe=t("span",null,"Showing",-1),ge={class:"font-medium"},fe=t("span",null,"to",-1),xe={class:"font-medium"},he=t("span",null,"of",-1),be={class:"font-medium"},ye=t("span",null,"results",-1),ve={class:"mt-6 flex flex-col"},Ce={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ke={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Ve={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Se={class:"bg-gray-100"},Be={class:"divide-x divide-gray-200"},Je=a(" # "),Te=a(" ID "),Pe=a(" Name "),we=a(" Errors "),Ne=a(" Inventory Status "),Ie=t("br",null,null,-1),Le=a(" (#Channel, Sales, Balance/Capacity) "),je=a(" Temp "),Ke=t("br",null,null,-1),Oe=a(" (Chamber) "),$e=a(" Temp "),Ue=t("br",null,null,-1),Ee=a(" (Evaporator) "),Ge=a(" Balance Stock "),Fe=a(" Out of Stock SKU "),Ae=a(" Status "),De=a(" Serial Num "),He=a(" Postcode "),qe=a(" Firmware Ver "),Me={class:"bg-white"},Re=t("br",null,null,-1),ze={class:"flex flex-col"},Ye={class:"font-bold"},Qe={key:0,class:"grid grid-cols-[105px_minmax(110px,_1fr)_100px]"},We={class:"font-semibold"},Xe={class:"text-blue-600 text-sm"},Ze={class:"flex flex-col items-center"},et=["onClick"],tt={class:"mt-1"},st={class:"flex flex-col items-center"},ot=["onClick"],nt=t("br",null,null,-1),lt=t("br",null,null,-1),at={class:"grid grid-cols-[90px_minmax(90px,_1fr)_90px] gap-1"},rt={class:"flex flex-col"},dt={class:"font-bold"},it={key:0},ut={class:"flex flex-col"},ct=t("span",{class:"font-bold"}," Drop Sensor ",-1),mt={key:1,class:"inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200"},_t={class:"flex flex-col"},pt=t("span",{class:"font-bold"}," Fan Speed ",-1),gt={class:"flex flex-col"},ft=t("span",{class:"font-bold"}," Door ",-1),xt={class:"flex flex-col"},ht=t("span",{class:"font-bold"}," Coin ",-1),bt={key:0},yt=t("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),vt=[yt],It={__name:"Index",props:{categories:Object,categoryGroups:Object,constTempError:Number,countries:Object,vends:Object,vendChannelErrors:Object},setup(d){const S=d,s=v({code:"",serialNum:"",customer_code:"",customer_name:"",categories:[],categoryGroups:[],is_binded_customer:"",tempHigherThan:"",vend_channel_error_id:"",is_online:"",sortKey:"",sortBy:!0,numberPerPage:""}),B=v([]),J=v([]),I=v([]),L=v([]),C=v([]);H(()=>{B.value=[{id:"",desc:"All"},{id:"errors_only",desc:"Errors Only"},...S.vendChannelErrors.data],J.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],s.value.vend_channel_error_id=B.value[0],s.value.numberPerPage=J.value[0],I.value=S.categories.data.map(_=>({id:_.id,name:_.name})),L.value=S.categoryGroups.data.map(_=>({id:_.id,name:_.name})),C.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],s.value.is_online=C.value[0],s.value.is_binded_customer=C.value[0]});function T(){N.Inertia.get("/vends",{...s.value,vend_channel_error_id:s.value.vend_channel_error_id.id,categories:s.value.categories.map(_=>_.id),categoryGroups:s.value.categoryGroups.map(_=>_.id),is_binded_customer:s.value.is_binded_customer.id,is_online:s.value.is_online.id,numberPerPage:s.value.numberPerPage.id},{preserveState:!0,replace:!0})}function j(_,n){N.Inertia.get("/vends/"+_+"/temp/"+n)}function E(){N.Inertia.get("/vends")}function h(_){s.value.sortKey=_,s.value.sortBy=!s.value.sortBy,T()}return(_,n)=>(u(),c(V,null,[o(P(q),{title:"Vending Machines"}),o(G,null,{header:l(()=>[R]),default:l(()=>{var K,O;return[t("div",z,[t("div",Y,[t("div",Q,[o(k,{placeholderStr:"Code",modelValue:s.value.code,"onUpdate:modelValue":n[0]||(n[0]=e=>s.value.code=e)},{default:l(()=>[W]),_:1},8,["modelValue"]),o(k,{placeholderStr:"Serial Num",modelValue:s.value.serialNum,"onUpdate:modelValue":n[1]||(n[1]=e=>s.value.serialNum=e)},{default:l(()=>[X]),_:1},8,["modelValue"]),o(k,{placeholderStr:"Number",modelValue:s.value.tempHigherThan,"onUpdate:modelValue":n[2]||(n[2]=e=>s.value.tempHigherThan=e)},{default:l(()=>[Z]),_:1},8,["modelValue"]),t("div",null,[ee,o(y,{modelValue:s.value.vend_channel_error_id,"onUpdate:modelValue":n[3]||(n[3]=e=>s.value.vend_channel_error_id=e),options:B.value,trackBy:"id",valueProp:"id",label:"desc",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),o(k,{placeholderStr:"Cust ID",modelValue:s.value.customer_code,"onUpdate:modelValue":n[4]||(n[4]=e=>s.value.customer_code=e)},{default:l(()=>[te]),_:1},8,["modelValue"]),o(k,{placeholderStr:"Cust Name",modelValue:s.value.customer_name,"onUpdate:modelValue":n[5]||(n[5]=e=>s.value.customer_name=e)},{default:l(()=>[se]),_:1},8,["modelValue"]),t("div",null,[oe,o(y,{modelValue:s.value.categories,"onUpdate:modelValue":n[6]||(n[6]=e=>s.value.categories=e),options:I.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[ne,o(y,{modelValue:s.value.categoryGroups,"onUpdate:modelValue":n[7]||(n[7]=e=>s.value.categoryGroups=e),options:L.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[le,o(y,{modelValue:s.value.is_online,"onUpdate:modelValue":n[8]||(n[8]=e=>s.value.is_online=e),options:C.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[ae,o(y,{modelValue:s.value.is_binded_customer,"onUpdate:modelValue":n[9]||(n[9]=e=>s.value.is_binded_customer=e),options:C.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),t("div",re,[t("div",de,[t("div",ie,[o(U,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[10]||(n[10]=e=>T())},{default:l(()=>[o(P(F),{class:"h-4 w-4","aria-hidden":"true"}),ue]),_:1}),o(U,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[11]||(n[11]=e=>E())},{default:l(()=>[o(P(A),{class:"h-4 w-4","aria-hidden":"true"}),ce]),_:1})])]),t("div",me,[t("p",_e,[pe,t("span",ge,r((K=d.vends.meta.from)!=null?K:0),1),fe,t("span",xe,r((O=d.vends.meta.to)!=null?O:0),1),he,t("span",be,r(d.vends.meta.total),1),ye]),o(y,{modelValue:s.value.numberPerPage,"onUpdate:modelValue":n[12]||(n[12]=e=>s.value.numberPerPage=e),options:J.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:T},null,8,["modelValue","options"])])])]),t("div",ve,[t("div",Ce,[t("div",ke,[t("table",Ve,[t("thead",Se,[t("tr",Be,[o(x,null,{default:l(()=>[Je]),_:1}),o(b,{modelName:"vends.code",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:n[13]||(n[13]=e=>h("vends.code"))},{default:l(()=>[Te]),_:1},8,["sortKey","sortBy"]),o(x,null,{default:l(()=>[Pe]),_:1}),o(x,null,{default:l(()=>[we]),_:1}),o(x,null,{default:l(()=>[Ne,Ie,Le]),_:1}),o(b,{modelName:"temp",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:n[14]||(n[14]=e=>h("temp"))},{default:l(()=>[je,Ke,Oe]),_:1},8,["sortKey","sortBy"]),o(b,{modelName:"parameter_json->t2",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:n[15]||(n[15]=e=>h("parameter_json->t2"))},{default:l(()=>[$e,Ue,Ee]),_:1},8,["sortKey","sortBy"]),o(b,{modelName:"vend_channel_totals_json->balancePercent",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:n[16]||(n[16]=e=>h("vend_channel_totals_json->balancePercent"))},{default:l(()=>[Ge]),_:1},8,["sortKey","sortBy"]),o(b,{modelName:"vend_channel_totals_json->outOfStockSkuPercent",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:n[17]||(n[17]=e=>h("vend_channel_totals_json->outOfStockSkuPercent"))},{default:l(()=>[Fe]),_:1},8,["sortKey","sortBy"]),o(x,null,{default:l(()=>[Ae]),_:1}),o(x,null,{default:l(()=>[De]),_:1}),o(b,{modelName:"addresses.postcode",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:n[18]||(n[18]=e=>h("addresses.postcode"))},{default:l(()=>[He]),_:1},8,["sortKey","sortBy"]),o(x,null,{default:l(()=>[qe]),_:1})])]),t("tbody",Me,[(u(!0),c(V,null,w(d.vends.data,(e,m)=>(u(),c("tr",{key:m,class:"divide-x divide-gray-200"},[o(p,{currentIndex:m,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[a(r(d.vends.meta.from+m),1)]),_:2},1032,["currentIndex","totalLength"]),o(p,{currentIndex:m,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[a(r(e.code),1)]),_:2},1032,["currentIndex","totalLength"]),o(p,{currentIndex:m,totalLength:d.vends.length,inputClass:"text-left"},{default:l(()=>[a(r(e.latestVendBinding&&e.latestVendBinding.customer?e.latestVendBinding.customer.code:null)+" ",1),Re,a(" "+r(e.latestVendBinding&&e.latestVendBinding.customer?e.latestVendBinding.customer.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),o(p,{currentIndex:m,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[(u(!0),c(V,null,w(e.vendChannelErrorLogsJson,i=>(u(),c("span",{class:f(["inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border",[i.is_error_cleared?"bg-green-100 text-green-800":"bg-red-100 text-red-800"]])},[t("div",ze,[t("div",null,[a(" #"+r(i.vendChannel?i.vendChannel.code:i.vend_channel.code)+", ",1),t("span",Ye," ("+r(i.vendChannelError?i.vendChannelError.code:i.vend_channel_error.code)+") ",1)]),t("div",null,r(i.created_at),1)])],2))),256))]),_:2},1032,["currentIndex","totalLength"]),o(p,{currentIndex:m,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[e.vendChannelsJson?(u(),c("div",Qe,[(u(!0),c(V,null,w(e.vendChannelsJson.filter(i=>i.code>=10&&i.code<=69),(i,$)=>(u(),c("span",{class:f(["inline-flex justify-evenly items-center rounded px-1 py-0.5 text-xs font-medium border min-w-full",[$>0&&String(i.code)[0]!==String(e.vendChannelsJson[$-1].code)[0]?"col-start-1":""]])},[t("div",We," #"+r(i.code)+", ",1),t("div",Xe,r(i.capacity-i.qty)+", ",1),t("div",{class:f([i.qty<=2?"text-red-700":"text-green-700"])},r(i.qty)+"/"+r(i.capacity),3)],2))),256))])):g("",!0)]),_:2},1032,["currentIndex","totalLength"]),o(p,{currentIndex:m,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[t("div",Ze,[t("button",{type:"button",class:f(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.temp>-15?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600"]]),onClick:i=>j(e.id,1)},r(e.is_temp_error?"Error":e.temp),11,et),t("span",tt,r(e.temp_updated_at),1)])]),_:2},1032,["currentIndex","totalLength"]),o(p,{currentIndex:m,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[t("div",st,[e.parameterJson&&e.parameterJson.t2?(u(),c("button",{key:0,type:"button",class:f(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.temp>-15||e.parameterJson.t2==d.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600"]]),onClick:i=>j(e.id,2)},r(e.parameterJson.t2==d.constTempError?"Error":e.parameterJson.t2/10),11,ot)):g("",!0)])]),_:2},1032,["currentIndex","totalLength"]),o(p,{currentIndex:m,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[e.vendChannelTotalsJson?(u(),c("span",{key:0,class:f([e.vendChannelTotalsJson.balancePercent<=30?"text-red-700":e.vendChannelTotalsJson.balancePercent>60?"":"text-blue-700"])},[a(r(e.vendChannelTotalsJson.qty)+"/ "+r(e.vendChannelTotalsJson.capacity)+" ",1),nt,a(" ("+r(e.vendChannelTotalsJson.balancePercent)+"%) ",1)],2)):g("",!0)]),_:2},1032,["currentIndex","totalLength"]),o(p,{currentIndex:m,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[e.vendChannelTotalsJson?(u(),c("span",{key:0,class:f([e.vendChannelTotalsJson.outOfStockSkuPercent>40?"text-red-700":""])},[a(r(e.vendChannelTotalsJson.outOfStockSku)+"/ "+r(e.vendChannelTotalsJson.count)+" ",1),lt,a(" ("+r(e.vendChannelTotalsJson.outOfStockSkuPercent)+"%) ",1)],2)):g("",!0)]),_:2},1032,["currentIndex","totalLength"]),o(p,{currentIndex:m,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[t("div",at,[t("div",{class:f(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.is_online?"bg-green-200":"bg-red-200"]])},[t("div",rt,[t("span",dt,r(e.is_online?"Online":"Offline"),1),e.last_updated_at?(u(),c("span",it,r(e.last_updated_at),1)):g("",!0)])],2),e.parameterJson&&e.parameterJson.Sensor?(u(),c("div",{key:0,class:f(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.Sensor==1?"bg-green-200":"bg-red-200"]])},[t("div",ut,[ct,t("span",null,r(e.parameterJson.Sensor==1?"Active":"Inactive"),1)])],2)):g("",!0),e.parameterJson&&e.parameterJson.fan?(u(),c("div",mt,[t("div",_t,[pt,t("span",null,r(e.parameterJson.fan),1)])])):g("",!0),e.parameterJson&&e.parameterJson.door?(u(),c("div",{key:2,class:f(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.door=="close"?"bg-green-200":"bg-red-200"]])},[t("div",gt,[ft,t("span",null,r(e.parameterJson.door=="open"?"Open":"Close"),1)])],2)):g("",!0),e.parameterJson&&e.parameterJson.CoinCnt?(u(),c("div",{key:3,class:f(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.CoinCnt>1600?"bg-green-200":"bg-red-200"]])},[t("div",xt,[ht,t("span",null,r((e.parameterJson.CoinCnt/100).toFixed(2)),1)])],2)):g("",!0)])]),_:2},1032,["currentIndex","totalLength"]),o(p,{currentIndex:m,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[a(r(e.serial_num),1)]),_:2},1032,["currentIndex","totalLength"]),o(p,{currentIndex:m,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[a(r(e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.deliveryAddress?e.latestVendBinding.customer.deliveryAddress.postcode:null),1)]),_:2},1032,["currentIndex","totalLength"]),o(p,{currentIndex:m,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[a(r(e.firmware_ver),1)]),_:2},1032,["currentIndex","totalLength"])]))),128)),d.vends.data.length?g("",!0):(u(),c("tr",bt,vt))])])]),d.vends.data.length?(u(),M(D,{key:0,links:d.vends.links,meta:d.vends.meta},null,8,["links","meta"])):g("",!0)])])])]}),_:1})],64))}};export{It as default};
