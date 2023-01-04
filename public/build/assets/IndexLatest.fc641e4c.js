import{_ as D}from"./Authenticated.ed173437.js";import{_ as O}from"./Button.24c8bfa0.js";import{_ as f,r as F,T as g,a as y,b as H,c as u}from"./TableHeadSort.362a212c.js";import{_ as v}from"./MultiSelect.eca5a602.js";import{i as p,j as E,o as _,g as h,a as t,b as S,w as l,F as b,H as A,d as n,t as d,m as k,p as U,c as J,f as s,J as I,n as w}from"./app.7e9af5a6.js";import{r as M}from"./BackspaceIcon.fa93b2fa.js";import"./open-closed.44e72380.js";import"./use-resolve-button-type.550dcd40.js";import"./RectangleStackIcon.29ba1ab7.js";import"./_plugin-vue_export-helper.cdc0426e.js";const q=n("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines (List) ",-1),R={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},z={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Q={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},W=s(" Vend ID "),X=s(" Serial Num "),Y=s(" Temp >> "),Z=n("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Errors? ",-1),ee=s(" Cust ID "),te=s(" Cust ID Name "),ne=n("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),le=n("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),oe={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},se={class:"mt-3"},ae={class:"flex space-x-1"},re=n("span",null," Search ",-1),de=n("span",null," Reset ",-1),ie={class:"flex flex-col space-y-2"},ue={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ce=n("span",null,"Showing",-1),me={class:"font-medium"},ge=n("span",null,"to",-1),_e={class:"font-medium"},he=n("span",null,"of",-1),fe={class:"font-medium"},pe=n("span",null,"results",-1),xe={class:"mt-6 flex flex-col"},ye={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ve={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},be={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Ve={class:"bg-gray-100"},Be={class:"divide-x divide-gray-200"},Ce=s(" # "),Le=s(" Code "),Se=s(" Temp "),ke=s(" Name "),Ie=s(" Category "),we=s(" Channel Status "),Ne=s(" Inventory Status "),Pe=s(" Balance Stock "),Ke=s(" Out of Stock SKU "),Te=s(" Last Temp "),$e=s(" Coin Amount "),Oe=s(" Serial Num "),Ue=s(" Firmware Ver "),Ge=s(" Door Opening? "),je=s(" Sensor Normal? "),De={class:"bg-white"},Fe={class:"flex flex-col"},He=["onClick"],Ee=n("br",null,null,-1),Ae=n("br",null,null,-1),Je={class:"flex flex-col"},Me={class:"font-bold"},qe={class:"grid grid-cols-[120px_minmax(120px,_1fr)_120px] gap-1"},Re={class:"font-semibold"},ze={class:"text-blue-600 text-sm pl-1"},Qe={class:"pl-1"},We={key:0},Xe=n("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ye=[Xe],it={__name:"IndexLatest",props:{categories:Object,categoryGroups:Object,vends:Object,vendChannelErrors:Object},setup(r){const V=r,o=p({code:"",serialNum:"",customer_code:"",customer_name:"",categories:[],categoryGroups:[],tempHigherThan:"",vend_channel_error_id:"",sortKey:"",sortBy:!0,numberPerPage:""}),B=p([]),C=p([]),N=p([]),P=p([]);E(()=>{B.value=[{id:"",desc:"All"},{id:"errors_only",desc:"Errors Only"},...V.vendChannelErrors.data],C.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],o.value.vend_channel_error_id=B.value[0],o.value.numberPerPage=C.value[0],N.value=V.categories.data.map(c=>({id:c.id,name:c.name})),P.value=V.categoryGroups.data.map(c=>({id:c.id,name:c.name}))});function L(){I.Inertia.get("/vends",{...o.value,vend_channel_error_id:o.value.vend_channel_error_id.id,categories:o.value.categories.map(c=>c.id),categoryGroups:o.value.categoryGroups.map(c=>c.id),numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0})}function G(c){I.Inertia.get("/vends/"+c+"/temp")}function j(){I.Inertia.get("/vends")}function x(c){o.value.sortKey=c,o.value.sortBy=!this.filters.sortBy,L()}return(c,a)=>(_(),h(b,null,[t(S(A),{title:"Vending Machines"}),t(D,null,{header:l(()=>[q]),default:l(()=>{var K,T;return[n("div",R,[n("div",z,[n("div",Q,[t(f,{placeholderStr:"Code",modelValue:o.value.code,"onUpdate:modelValue":a[0]||(a[0]=e=>o.value.code=e)},{default:l(()=>[W]),_:1},8,["modelValue"]),t(f,{placeholderStr:"Serial Num",modelValue:o.value.serialNum,"onUpdate:modelValue":a[1]||(a[1]=e=>o.value.serialNum=e)},{default:l(()=>[X]),_:1},8,["modelValue"]),t(f,{placeholderStr:"Number",modelValue:o.value.tempHigherThan,"onUpdate:modelValue":a[2]||(a[2]=e=>o.value.tempHigherThan=e)},{default:l(()=>[Y]),_:1},8,["modelValue"]),n("div",null,[Z,t(v,{modelValue:o.value.vend_channel_error_id,"onUpdate:modelValue":a[3]||(a[3]=e=>o.value.vend_channel_error_id=e),options:B.value,trackBy:"id",valueProp:"id",label:"desc",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t(f,{placeholderStr:"Cust ID",modelValue:o.value.customer_code,"onUpdate:modelValue":a[4]||(a[4]=e=>o.value.customer_code=e)},{default:l(()=>[ee]),_:1},8,["modelValue"]),t(f,{placeholderStr:"Cust ID Name",modelValue:o.value.customer_name,"onUpdate:modelValue":a[5]||(a[5]=e=>o.value.customer_name=e)},{default:l(()=>[te]),_:1},8,["modelValue"]),n("div",null,[ne,t(v,{modelValue:o.value.categories,"onUpdate:modelValue":a[6]||(a[6]=e=>o.value.categories=e),options:N.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),n("div",null,[le,t(v,{modelValue:o.value.categoryGroups,"onUpdate:modelValue":a[7]||(a[7]=e=>o.value.categoryGroups=e),options:P.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),n("div",oe,[n("div",se,[n("div",ae,[t(O,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[8]||(a[8]=e=>L())},{default:l(()=>[t(S(F),{class:"h-4 w-4","aria-hidden":"true"}),re]),_:1}),t(O,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[9]||(a[9]=e=>j())},{default:l(()=>[t(S(M),{class:"h-4 w-4","aria-hidden":"true"}),de]),_:1})])]),n("div",ie,[n("p",ue,[ce,n("span",me,d((K=r.vends.meta.from)!=null?K:0),1),ge,n("span",_e,d((T=r.vends.meta.to)!=null?T:0),1),he,n("span",fe,d(r.vends.meta.total),1),pe]),t(v,{modelValue:o.value.numberPerPage,"onUpdate:modelValue":a[10]||(a[10]=e=>o.value.numberPerPage=e),options:C.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:L},null,8,["modelValue","options"])])])]),n("div",xe,[n("div",ye,[n("div",ve,[n("table",be,[n("thead",Ve,[n("tr",Be,[t(g,null,{default:l(()=>[Ce]),_:1}),t(y,{modelName:"code",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:a[11]||(a[11]=e=>x("code"))},{default:l(()=>[Le]),_:1},8,["sortKey","sortBy"]),t(y,{modelName:"temp",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:a[12]||(a[12]=e=>x("temp"))},{default:l(()=>[Se]),_:1},8,["sortKey","sortBy"]),t(g,null,{default:l(()=>[ke]),_:1}),t(g,null,{default:l(()=>[Ie]),_:1}),t(g,null,{default:l(()=>[we]),_:1}),t(g,null,{default:l(()=>[Ne]),_:1}),t(g,null,{default:l(()=>[Pe]),_:1}),t(g,null,{default:l(()=>[Ke]),_:1}),t(y,{modelName:"temp_updated_at",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:a[13]||(a[13]=e=>x("temp_updated_at"))},{default:l(()=>[Te]),_:1},8,["sortKey","sortBy"]),t(y,{modelName:"coin_amount",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:a[14]||(a[14]=e=>x("coin_amount"))},{default:l(()=>[$e]),_:1},8,["sortKey","sortBy"]),t(g,null,{default:l(()=>[Oe]),_:1}),t(g,null,{default:l(()=>[Ue]),_:1}),t(g,null,{default:l(()=>[Ge]),_:1}),t(g,null,{default:l(()=>[je]),_:1})])]),n("tbody",De,[(_(!0),h(b,null,k(r.vends.data,(e,i)=>(_(),h("tr",{key:e.id,class:"divide-x divide-gray-200"},[t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},{default:l(()=>[s(d(r.vends.meta.from+i),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},{default:l(()=>[s(d(e.code),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},{default:l(()=>[n("div",Fe,[n("button",{type:"button",class:w(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.temp>-15?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600"]]),onClick:m=>G(e.id)},d(e.is_temp_error?"Error":e.temp),11,He)])]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-left"},{default:l(()=>[s(d(e.latestVendBinding&&e.latestVendBinding.customer?e.latestVendBinding.customer.code:null)+" ",1),Ee,s(" "+d(e.latestVendBinding&&e.latestVendBinding.customer?e.latestVendBinding.customer.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-left"},{default:l(()=>[s(d(e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.category?e.latestVendBinding.customer.category.name:null)+" ",1),Ae,s(" "+d(e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.category&&e.latestVendBinding.customer.category.category_group?e.latestVendBinding.customer.category.category_group.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},{default:l(()=>[(_(!0),h(b,null,k(e.vendChannelErrorLogsJson,m=>(_(),h("span",{class:w(["inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border",[m.is_error_cleared?"bg-green-100 text-green-800":"bg-red-100 text-red-800"]])},[n("div",Je,[n("div",null,[s(" #"+d(m.vend_channel.code)+", ",1),n("span",Me," ("+d(m.vend_channel_error.code)+") ",1)]),n("div",null,d(m.created_at),1)])],2))),256))]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},{default:l(()=>[n("div",qe,[(_(!0),h(b,null,k(e.vendChannelsJson,(m,$)=>(_(),h("div",{class:w(["inline-flex justify-between items-center rounded px-2.5 py-0.5 text-xs font-medium border min-w-full",[$>0&&String(m.code)[0]!==String(e.vendChannelsJson[$-1].code)[0]?"col-start-1":""]])},[n("div",Re," #"+d(m.code)+", ",1),n("div",ze,d(m.capacity-m.qty)+", ",1),n("div",Qe,d(m.qty)+"/"+d(m.capacity),1)],2))),256))])]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},null,8,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},null,8,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},{default:l(()=>[s(d(e.temp_updated_at),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-right"},{default:l(()=>[s(d(e.coin_amount),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},{default:l(()=>[s(d(e.serial_num),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},{default:l(()=>[s(d(e.firmware_ver),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},{default:l(()=>[s(d(e.is_door_open),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},{default:l(()=>[s(d(e.is_sensor_normal),1)]),_:2},1032,["currentIndex","totalLength"])]))),128)),r.vends.data.length?U("",!0):(_(),h("tr",We,Ye))])]),r.vends.data.length?(_(),J(H,{key:0,links:r.vends.links,meta:r.vends.meta},null,8,["links","meta"])):U("",!0)])])])])]}),_:1})],64))}};export{it as default};
