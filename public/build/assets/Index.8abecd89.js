import{_ as E}from"./Authenticated.ca8fcace.js";import{_ as A}from"./Button.bb6923ed.js";import{r as G,a as F,T as x,_ as b,b as H,c as g}from"./TableHeadSort.afc702c4.js";import{_ as V}from"./SearchInput.d30185b4.js";import{_ as v}from"./MultiSelect.b1c80171.js";import{i as h,j as q,o as u,g as m,a as o,b as w,w as l,F as k,H as M,d as t,t as r,m as I,p,c as R,f as a,J as L,n as f}from"./app.431336d2.js";import"./open-closed.9a06f277.js";import"./use-resolve-button-type.f72a89ea.js";import"./RectangleStackIcon.69e90b52.js";import"./_plugin-vue_export-helper.cdc0426e.js";const z=t("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines (List) ",-1),Y={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},Q={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},W={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},X=a(" Vend ID "),Z=a(" Serial Num "),ee=a(" Temp >> "),te=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Errors? ",-1),ne=a(" Cust ID "),oe=a(" Cust ID Name "),se=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),le=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),ae=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Online? ",-1),re=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Country ",-1),de={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ie={class:"mt-3"},ue={class:"flex space-x-1"},ce=t("span",null," Search ",-1),me=t("span",null," Reset ",-1),_e={class:"flex flex-col space-y-2"},ge={class:"text-sm text-gray-700 leading-5 flex space-x-1"},pe=t("span",null,"Showing",-1),fe={class:"font-medium"},xe=t("span",null,"to",-1),he={class:"font-medium"},ye=t("span",null,"of",-1),be={class:"font-medium"},ve=t("span",null,"results",-1),Ve={class:"mt-6 flex flex-col"},Ce={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ke={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Be={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Se={class:"bg-gray-100"},Je={class:"divide-x divide-gray-200"},Te=a(" # "),Pe=a(" ID "),we=a(" Name "),Ie=a(" Category "),Le=a(" Errors "),Ne=a(" Inventory Status "),Oe=t("br",null,null,-1),je=a(" (#, Sales, Balance/Capacity) "),Ke=a(" Temp "),$e=t("br",null,null,-1),Ue=a(" (Chamber) "),Ae=a(" Temp "),De=t("br",null,null,-1),Ee=a(" (Evaporator) "),Ge=a(" Balance Stock "),Fe=a(" Out of Stock SKU "),He=a(" Status "),qe=a(" Serial Num "),Me=a(" Postcode "),Re=a(" Firmware Ver "),ze={class:"bg-white"},Ye=t("br",null,null,-1),Qe=t("br",null,null,-1),We={class:"flex flex-col"},Xe={class:"font-bold"},Ze={class:"grid grid-cols-[105px_minmax(110px,_1fr)_100px]"},et={class:"font-semibold"},tt={class:"text-blue-600 text-sm"},nt={class:"flex flex-col items-center"},ot=["onClick"],st={class:"mt-1"},lt={class:"flex flex-col items-center"},at=["onClick"],rt=t("br",null,null,-1),dt=t("br",null,null,-1),it={class:"grid grid-cols-[90px_minmax(90px,_1fr)_90px] gap-1"},ut={class:"flex flex-col"},ct={class:"font-bold"},mt={key:0},_t={class:"flex flex-col"},gt=t("span",{class:"font-bold"}," Drop Sensor ",-1),pt={key:1,class:"inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200"},ft={class:"flex flex-col"},xt=t("span",{class:"font-bold"}," Fan Speed ",-1),ht={class:"flex flex-col"},yt=t("span",{class:"font-bold"}," Door ",-1),bt={class:"flex flex-col"},vt=t("span",{class:"font-bold"}," Coin ",-1),Vt={key:0},Ct={key:0},kt=t("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Bt=[kt],Kt={__name:"Index",props:{categories:Object,categoryGroups:Object,constTempError:Number,countries:Object,vends:Object,vendChannelErrors:Object},setup(d){const C=d,n=h({code:"",serialNum:"",customer_code:"",customer_name:"",categories:[],categoryGroups:[],country_id:"",tempHigherThan:"",vend_channel_error_id:"",is_online:"",sortKey:"",sortBy:!0,numberPerPage:""}),B=h([]),S=h([]),N=h([]),O=h([]),J=h([]),T=h([]);q(()=>{B.value=[{id:"",desc:"All"},{id:"errors_only",desc:"Errors Only"},...C.vendChannelErrors.data],S.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],n.value.vend_channel_error_id=B.value[0],n.value.numberPerPage=S.value[0],N.value=C.categories.data.map(c=>({id:c.id,name:c.name})),O.value=C.categoryGroups.data.map(c=>({id:c.id,name:c.name})),T.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],n.value.is_online=T.value[1],J.value=[{id:"0",name:"All"},...C.countries.data.map(c=>({id:c.id,name:c.name}))],n.value.country_id=J.value[1]});function P(){L.Inertia.get("/vends",{...n.value,vend_channel_error_id:n.value.vend_channel_error_id.id,categories:n.value.categories.map(c=>c.id),categoryGroups:n.value.categoryGroups.map(c=>c.id),country_id:n.value.country_id.id,is_online:n.value.is_online.id,numberPerPage:n.value.numberPerPage.id},{preserveState:!0,replace:!0})}function j(c,s){L.Inertia.get("/vends/"+c+"/temp/"+s)}function D(){L.Inertia.get("/vends")}function y(c){n.value.sortKey=c,n.value.sortBy=!n.value.sortBy,P()}return(c,s)=>(u(),m(k,null,[o(w(M),{title:"Vending Machines"}),o(E,null,{header:l(()=>[z]),default:l(()=>{var K,$;return[t("div",Y,[t("div",Q,[t("div",W,[o(V,{placeholderStr:"Code",modelValue:n.value.code,"onUpdate:modelValue":s[0]||(s[0]=e=>n.value.code=e)},{default:l(()=>[X]),_:1},8,["modelValue"]),o(V,{placeholderStr:"Serial Num",modelValue:n.value.serialNum,"onUpdate:modelValue":s[1]||(s[1]=e=>n.value.serialNum=e)},{default:l(()=>[Z]),_:1},8,["modelValue"]),o(V,{placeholderStr:"Number",modelValue:n.value.tempHigherThan,"onUpdate:modelValue":s[2]||(s[2]=e=>n.value.tempHigherThan=e)},{default:l(()=>[ee]),_:1},8,["modelValue"]),t("div",null,[te,o(v,{modelValue:n.value.vend_channel_error_id,"onUpdate:modelValue":s[3]||(s[3]=e=>n.value.vend_channel_error_id=e),options:B.value,trackBy:"id",valueProp:"id",label:"desc",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),o(V,{placeholderStr:"Cust ID",modelValue:n.value.customer_code,"onUpdate:modelValue":s[4]||(s[4]=e=>n.value.customer_code=e)},{default:l(()=>[ne]),_:1},8,["modelValue"]),o(V,{placeholderStr:"Cust ID Name",modelValue:n.value.customer_name,"onUpdate:modelValue":s[5]||(s[5]=e=>n.value.customer_name=e)},{default:l(()=>[oe]),_:1},8,["modelValue"]),t("div",null,[se,o(v,{modelValue:n.value.categories,"onUpdate:modelValue":s[6]||(s[6]=e=>n.value.categories=e),options:N.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[le,o(v,{modelValue:n.value.categoryGroups,"onUpdate:modelValue":s[7]||(s[7]=e=>n.value.categoryGroups=e),options:O.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[ae,o(v,{modelValue:n.value.is_online,"onUpdate:modelValue":s[8]||(s[8]=e=>n.value.is_online=e),options:T.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[re,o(v,{modelValue:n.value.country_id,"onUpdate:modelValue":s[9]||(s[9]=e=>n.value.country_id=e),options:J.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),t("div",de,[t("div",ie,[t("div",ue,[o(A,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[10]||(s[10]=e=>P())},{default:l(()=>[o(w(G),{class:"h-4 w-4","aria-hidden":"true"}),ce]),_:1}),o(A,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[11]||(s[11]=e=>D())},{default:l(()=>[o(w(F),{class:"h-4 w-4","aria-hidden":"true"}),me]),_:1})])]),t("div",_e,[t("p",ge,[pe,t("span",fe,r((K=d.vends.meta.from)!=null?K:0),1),xe,t("span",he,r(($=d.vends.meta.to)!=null?$:0),1),ye,t("span",be,r(d.vends.meta.total),1),ve]),o(v,{modelValue:n.value.numberPerPage,"onUpdate:modelValue":s[12]||(s[12]=e=>n.value.numberPerPage=e),options:S.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:P},null,8,["modelValue","options"])])])]),t("div",Ve,[t("div",Ce,[t("div",ke,[t("table",Be,[t("thead",Se,[t("tr",Je,[o(x,null,{default:l(()=>[Te]),_:1}),o(b,{modelName:"code",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:s[13]||(s[13]=e=>y("code"))},{default:l(()=>[Pe]),_:1},8,["sortKey","sortBy"]),o(x,null,{default:l(()=>[we]),_:1}),o(x,null,{default:l(()=>[Ie]),_:1}),o(x,null,{default:l(()=>[Le]),_:1}),o(x,null,{default:l(()=>[Ne,Oe,je]),_:1}),o(b,{modelName:"temp",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:s[14]||(s[14]=e=>y("temp"))},{default:l(()=>[Ke,$e,Ue]),_:1},8,["sortKey","sortBy"]),o(b,{modelName:"parameter_json->t2",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:s[15]||(s[15]=e=>y("parameter_json->t2"))},{default:l(()=>[Ae,De,Ee]),_:1},8,["sortKey","sortBy"]),o(b,{modelName:"vend_channel_totals_json->balancePercent",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:s[16]||(s[16]=e=>y("vend_channel_totals_json->balancePercent"))},{default:l(()=>[Ge]),_:1},8,["sortKey","sortBy"]),o(b,{modelName:"vend_channel_totals_json->outOfStockSkuPercent",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:s[17]||(s[17]=e=>y("vend_channel_totals_json->outOfStockSkuPercent"))},{default:l(()=>[Fe]),_:1},8,["sortKey","sortBy"]),o(x,null,{default:l(()=>[He]),_:1}),o(x,null,{default:l(()=>[qe]),_:1}),o(b,{modelName:"latestVendBinding.customer.deliveryAddress.postcode",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:s[18]||(s[18]=e=>y("latestVendBinding.customer.deliveryAddress.postcode"))},{default:l(()=>[Me]),_:1},8,["sortKey","sortBy"]),o(x,null,{default:l(()=>[Re]),_:1})])]),t("tbody",ze,[(u(!0),m(k,null,I(d.vends.data,(e,_)=>(u(),m("tr",{key:e.id,class:"divide-x divide-gray-200"},[o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[a(r(d.vends.meta.from+_),1)]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[a(r(e.code),1)]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-left"},{default:l(()=>[a(r(e.latestVendBinding&&e.latestVendBinding.customer?e.latestVendBinding.customer.code:null)+" ",1),Ye,a(" "+r(e.latestVendBinding&&e.latestVendBinding.customer?e.latestVendBinding.customer.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-left"},{default:l(()=>[a(r(e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.category?e.latestVendBinding.customer.category.name:null)+" ",1),Qe,a(" "+r(e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.category&&e.latestVendBinding.customer.category.category_group?e.latestVendBinding.customer.category.category_group.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[(u(!0),m(k,null,I(e.vendChannelErrorLogsJson,i=>(u(),m("span",{class:f(["inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border",[i.is_error_cleared?"bg-green-100 text-green-800":"bg-red-100 text-red-800"]])},[t("div",We,[t("div",null,[a(" #"+r(i.vendChannel?i.vendChannel.code:i.vend_channel.code)+", ",1),t("span",Xe," ("+r(i.vendChannelError?i.vendChannelError.code:i.vend_channel_error.code)+") ",1)]),t("div",null,r(i.created_at),1)])],2))),256))]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[t("div",Ze,[(u(!0),m(k,null,I(e.vendChannelsJson.filter(i=>i.code>=10&&i.code<=69),(i,U)=>(u(),m("span",{class:f(["inline-flex justify-evenly items-center rounded px-1 py-0.5 text-xs font-medium border min-w-full",[U>0&&String(i.code)[0]!==String(e.vendChannelsJson[U-1].code)[0]?"col-start-1":""]])},[t("div",et," #"+r(i.code)+", ",1),t("div",tt,r(i.capacity-i.qty)+", ",1),t("div",{class:f([i.qty<=2?"text-red-700":"text-green-700"])},r(i.qty)+"/"+r(i.capacity),3)],2))),256))])]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[t("div",nt,[t("button",{type:"button",class:f(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.temp>-15?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600"]]),onClick:i=>j(e.id,1)},r(e.is_temp_error?"Error":e.temp),11,ot),t("span",st,r(e.temp_updated_at),1)])]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[t("div",lt,[e.parameterJson&&e.parameterJson.t2?(u(),m("button",{key:0,type:"button",class:f(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.temp>-15||e.parameterJson.t2==d.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600"]]),onClick:i=>j(e.id,2)},r(e.parameterJson.t2==d.constTempError?"Error":e.parameterJson.t2/10),11,at)):p("",!0)])]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[e.vendChannelTotalsJson?(u(),m("span",{key:0,class:f([e.vendChannelTotalsJson.balancePercent<=30?"text-red-700":e.vendChannelTotalsJson.balancePercent>60?"":"text-blue-700"])},[a(r(e.vendChannelTotalsJson.qty)+"/ "+r(e.vendChannelTotalsJson.capacity)+" ",1),rt,a(" ("+r(e.vendChannelTotalsJson.balancePercent)+"%) ",1)],2)):p("",!0)]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[e.vendChannelTotalsJson?(u(),m("span",{key:0,class:f([e.vendChannelTotalsJson.outOfStockSkuPercent>40?"text-red-700":""])},[a(r(e.vendChannelTotalsJson.outOfStockSku)+"/ "+r(e.vendChannelTotalsJson.count)+" ",1),dt,a(" ("+r(e.vendChannelTotalsJson.outOfStockSkuPercent)+"%) ",1)],2)):p("",!0)]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[t("div",it,[t("div",{class:f(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.is_online?"bg-green-200":"bg-red-200"]])},[t("div",ut,[t("span",ct,r(e.is_online?"Online":"Offline"),1),e.last_updated_at?(u(),m("span",mt,r(e.last_updated_at),1)):p("",!0)])],2),e.parameterJson&&e.parameterJson.Sensor?(u(),m("div",{key:0,class:f(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.Sensor==1?"bg-green-200":"bg-red-200"]])},[t("div",_t,[gt,t("span",null,r(e.parameterJson.Sensor==1?"Active":"Inactive"),1)])],2)):p("",!0),e.parameterJson&&e.parameterJson.fan?(u(),m("div",pt,[t("div",ft,[xt,t("span",null,r(e.parameterJson.fan),1)])])):p("",!0),e.parameterJson&&e.parameterJson.door?(u(),m("div",{key:2,class:f(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.door=="close"?"bg-green-200":"bg-red-200"]])},[t("div",ht,[yt,t("span",null,r(e.parameterJson.door=="open"?"Open":"Close"),1)])],2)):p("",!0),e.parameterJson&&e.parameterJson.CoinCnt?(u(),m("div",{key:3,class:f(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.CoinCnt>1600?"bg-green-200":"bg-red-200"]])},[t("div",bt,[vt,t("span",null,r((e.parameterJson.CoinCnt/100).toFixed(2)),1)])],2)):p("",!0)])]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[a(r(e.serial_num),1)]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.deliveryAddress?(u(),m("span",Vt,r(e.postcode),1)):p("",!0)]),_:2},1032,["currentIndex","totalLength"]),o(g,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[a(r(e.firmware_ver),1)]),_:2},1032,["currentIndex","totalLength"])]))),128)),d.vends.data.length?p("",!0):(u(),m("tr",Ct,Bt))])])]),d.vends.data.length?(u(),R(H,{key:0,links:d.vends.links,meta:d.vends.meta},null,8,["links","meta"])):p("",!0)])])])]}),_:1})],64))}};export{Kt as default};
